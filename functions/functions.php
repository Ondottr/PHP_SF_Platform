<?php declare( strict_types=1 );

use App\Kernel;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Deprecated;
use JetBrains\PhpStorm\ExpectedValues;
use PHP_SF\Framework\Http\Middleware\auth;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractCacheAdapter;
use PHP_SF\System\Classes\Exception\RouteParameterExpectedException;
use PHP_SF\System\Core\Cache\APCuCacheAdapter;
use PHP_SF\System\Core\Cache\MemcachedCacheAdapter;
use PHP_SF\System\Core\Cache\RedisCacheAdapter;
use PHP_SF\System\Core\Sessions;
use PHP_SF\System\Core\Translator;
use PHP_SF\System\Database\Redis;
use PHP_SF\System\Interface\UserInterface;
use PHP_SF\System\Router;
use Predis\Client;
use Predis\Pipeline\Pipeline;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

require_once __DIR__ . '/time_functions.php';
require_once __DIR__ . '/view_functions.php';

function em( string $connectionName ): EntityManager
{
    $kernel = Kernel::getInstance();

    return $kernel->getContainer()->get( 'doctrine.orm.' . $connectionName . '_entity_manager' );
}

function qb( string $connectionName ): QueryBuilder
{
    return em( $connectionName )->createQueryBuilder();
}


/**
 * @deprecated Use {@link rca()} function instead
 */
#[Deprecated( replacement: 'rca()' )]
function ra(): RedisCacheAdapter
{
    return RedisCacheAdapter::getInstance();
}


/**
 * This function returns an instance of one of the cache adapter classes ({@link RedisCacheAdapter} or {@link APCuCacheAdapter})
 */
function ca(
    #[ExpectedValues( [ null,
        AbstractCacheAdapter::APCU_CACHE_ADAPTER,
        AbstractCacheAdapter::REDIS_CACHE_ADAPTER,
        AbstractCacheAdapter::MEMCACHED_CACHE_ADAPTER
    ] )]
    string|null $cacheAdapter = null
): AbstractCacheAdapter {
    if ( $cacheAdapter === null ) {
        $isAPCuAvailable = function_exists( 'apcu_enabled' ) && apcu_enabled();
        if ( $isAPCuAvailable )
            $cacheAdapter = AbstractCacheAdapter::APCU_CACHE_ADAPTER;
        else
            $cacheAdapter = AbstractCacheAdapter::REDIS_CACHE_ADAPTER;
    }

    return match ( $cacheAdapter ) {
        AbstractCacheAdapter::APCU_CACHE_ADAPTER      => aca(),
        AbstractCacheAdapter::REDIS_CACHE_ADAPTER     => rca(),
        AbstractCacheAdapter::MEMCACHED_CACHE_ADAPTER => mca(),
        default                                       => throw new InvalidArgumentException( 'Invalid cache adapter' ),
    };
}


/**
 * This function returns {@link RedisCacheAdapter} class instance
 *
 * Use {@link ca()} instead for a more flexible way to get an cache adapter instance
 */
function rca(): RedisCacheAdapter
{
    return RedisCacheAdapter::getInstance();
}


/**
 * This function returns {@link APCuCacheAdapter} class instance
 *
 * Use {@link ca()} instead for a more flexible way to get an cache adapter instance
 *
 */
function aca(): APCuCacheAdapter
{
    return APCuCacheAdapter::getInstance();
}


/**
 * This function returns {@link MemcachedCacheAdapter} class instance
 *
 * Use {@link ca()} instead for a more flexible way to get an cache adapter instance
 */
function mca(): MemcachedCacheAdapter
{
    return MemcachedCacheAdapter::getInstance();
}


function rc(): Client
{
    return Redis::getClient();
}


function rp(): Pipeline
{
    return Redis::getPipeline();
}


function s(): Session
{
    return Sessions::getInstance();
}


/**
 * @param string      $routeName   Route name, first parameter from {@link Route} attribute
 * @param array       $pathParams  Route parameters to replace in route link
 *                                 Example: routeLink('user_profile', ['id' => 1])
 *                                 Route link: /user/{id}
 *                                 Result: /user/1
 * @param array       $queryParams Query parameters to add to route link
 *                                 Example: routeLink('user_profile', ['id' => 1], ['page' => 2])
 *                                 Route link: /user/{id}
 *                                 Result: /user/1?page=2
 * @param string|null $siteUrl     Site url to add to route link
 *                                 Example: routeLink('user_profile', ['id' => 1], ['page' => 2], 'https://example.com')
 *                                 Route link: /user/{id}
 *                                 Result: https://example.com/user/1?page=2
 *
 * @throws InvalidArgumentException If $siteUrl is not valid url
 * @throws RouteNotFoundException If route not exists in symfony routes and in routes from {@link Route} attribute
 * @throws RouteParameterExpectedException If route parameter is not provided in $with array and is required
 * @throws \Psr\SimpleCache\InvalidArgumentException If cache key is invalid
 */
function routeLink( string $routeName, array $pathParams = [], array $queryParams = [], string $siteUrl = null ): string
{
    $cacheKey = sprintf(
        'route_link_%s_%s_%s_%s',
        $routeName,
        md5( serialize( $pathParams ) ),
        md5( serialize( $queryParams ) ),
        md5( serialize( $siteUrl ) )
    );

    // Check if route link is cached
    if ( ca()->has( $cacheKey ) )
        // Return cached route link
        return ca()->get( $cacheKey );

    if ( $siteUrl !== null && filter_var( $siteUrl, FILTER_VALIDATE_URL ) === false )
        throw new InvalidArgumentException( 'Invalid site url' );

    /**
     * First check if route exists
     * If not then try to generate link from symfony routes
     * If symfony route not exists too, then throw an exception
     */
    try {
        if ( Router::isRouteExists( $routeName ) === false ) {
            $link = Kernel::getInstance()->getContainer()->get( 'router' )?->generate( $routeName, $pathParams );

            // If link is not null then cache it and return
            if ( $link !== null ) {
                ca()->set( $cacheKey, $link );

                return $link;
            }
        }

        // If route not exists in symfony routes and in routes from {@link Route} attribute
        throw new RouteNotFoundException;
    } catch ( RouteNotFoundException ) {

        // Get route link
        $link = ( $siteUrl !== null )
            ? $siteUrl . Router::getRouteLink( $routeName )
            : Router::getRouteLink( $routeName );

        // Check if parameters are provided
        if ( empty( $pathParams ) === false ) {
            // Replace parameters in route link
            foreach ( $pathParams as $propertyName => $propertyValue )
                $link = str_replace( sprintf( '{%s}', $propertyName ), (string)$propertyValue, $link );
        }

        // Check if route exists in routes from {@link Route} attribute
        if ( Router::isRouteExists( $routeName ) ) {
            // Get route info
            $routeInfo = Router::getRouteInfo( $routeName );

            // Check if route contains parameters
            if ( empty( $routeInfo['routeParams'] ) === false )
                // Loop through route parameters
                foreach ( $routeInfo['routeParams'] as $param )
                    // Check if parameter is provided to replace in route link
                    if ( array_key_exists( $param, $pathParams ) === false )
                        // If parameter is required then throw an exception
                        throw new RouteParameterExpectedException( $routeName, $param );

        }

        // Check if route link contains not replaces parameters
        // This means that route with this name not exists in routes from {@link Route} attribute
        if ( strpos( $link, '{$' ) )
            // Return route name
            return "#$routeName";

        // Check if query parameters are provided
        if ( empty( $queryParams ) === false )
            // Add query parameters to route link
            $link .= '?' . http_build_query( $queryParams );

        // Cache route link
        ca()->set( $cacheKey, $link );

        // Return route link
        return $link;
    }
}

/**
 * Translates a message key to the **current session locale** using Symfony's translator
 * with the `messages+intl-icu` domain (ICU MessageFormat).
 *
 * **Key naming convention:** semantic dot-notation in 3–4 parts:
 * ```
 * <domain>.<feature>.<element>
 * ```
 *
 * **Simple translation:**
 * ```php
 * _t( 'auth.login_form.title' )              // → 'Sign in'
 * _t( 'common.buttons.cancel' )              // → 'Cancel'
 * ```
 *
 * **Named ICU parameters** (`{param}` syntax — never positional `%s`):
 * ```php
 * _t( 'auth.register_form.welcome', [ 'name' => $user->getLogin() ] )
 * // YAML: 'auth.register_form.welcome': 'Welcome, {name}!'
 * ```
 *
 * **Pluralization** (`{count, plural, ...}`):
 * ```php
 * _t( 'notifications.unread_count', [ 'count' => 5 ] )
 * // YAML:
 * // notifications.unread_count: >-
 * //   {count, plural,
 * //     =0    {No notifications}
 * //     one   {# notification}
 * //     few   {# notifications}
 * //     many  {# notifications}
 * //     other {# notifications}
 * //   }
 * ```
 *
 * **Gender / declension** (`{field, select, ...}`):
 * ```php
 * _t( 'user.greeting', [ 'gender' => $user->getGender(), 'name' => $user->getName() ] )
 * // YAML:
 * // user.greeting: >-
 * //   {gender, select,
 * //     male   {Welcome, Mr. {name}}
 * //     female {Welcome, Ms. {name}}
 * //     other  {Welcome, {name}}
 * //   }
 * ```
 *
 * Output is wrapped in {@link nl2br()} — newlines in translations become `<br>` tags.
 *
 * Falls back to the raw `$key` string if no translation is found (no exception thrown).
 *
 * @param non-empty-string                                        $key        Translation key in dot-notation (e.g. `'auth.login_form.title'`)
 * @param array<non-empty-string, string|int|float|\DateTimeInterface> $parameters Named ICU parameters keyed by placeholder name (e.g. `['name' => 'John', 'count' => 5]`)
 *
 * @return string Translated and nl2br-wrapped string
 *
 * @see _tt() To translate to a specific locale regardless of current session
 * @see \PHP_SF\System\Core\Lang::getCurrentLocale()
 * @see \PHP_SF\System\Core\Lang::setCurrentLocale()
 */
function _t( string $key,
    #[ArrayShape( [
        // Keys are dynamic — they match the {placeholder} names defined in the translation YAML.
        // The types below cover all values accepted by PHP's IntlMessageFormatter:

        // --- ICU plural rule selector ({count, plural, one {...} other {...}}) ---
        'count'  => 'int',

        // --- ICU select/gender selector ({gender, select, male {...} female {...} other {...}}) ---
        'gender' => 'string',

        // --- ICU date/time formatting ({date, date, long}) ---
        'date'   => DateTimeInterface::class,

        // --- Generic named substitution ({name}, {field}, {value}, …) ---
        'string' => 'string|int|float',
    ] )]
    array $parameters = []
): string
{
    return nl2br( Translator::getInstance()->translate( $key, $parameters ) );
}

/**
 * Translates a message key to a **specific locale** using Symfony's translator
 * with the `messages+intl-icu` domain (ICU MessageFormat).
 *
 * Identical to {@link _t()} in every way except the locale is explicit rather than
 * read from the current session. Useful for generating content in the recipient's
 * language (e.g. emails, notifications) regardless of the current user's locale.
 *
 * **Simple translation to a specific locale:**
 * ```php
 * _tt( 'common.buttons.confirm', 'pl' )      // → Polish translation
 * _tt( 'auth.login_form.title', 'uk' )       // → Ukrainian translation
 * ```
 *
 * **With named ICU parameters:**
 * ```php
 * _tt( 'notifications.unread_count', $recipient->getLocale(), [ 'count' => 3 ] )
 * ```
 *
 * **Typical use case — email subject in recipient's language:**
 * ```php
 * $subject = _tt( 'mail.new_message.subject', $recipient->getLocale(), [
 *     'sender' => $sender->getName(),
 * ] );
 * ```
 *
 * Output is wrapped in {@link nl2br()} — newlines in translations become `<br>` tags.
 *
 * Falls back to the raw `$key` string if no translation is found for `$locale`.
 *
 * @param non-empty-string                                             $key        Translation key in dot-notation (e.g. `'mail.new_message.subject'`)
 * @param non-empty-string                                             $locale     Target locale key — must be a value present in {@link LANGUAGES_LIST} (e.g. `'en'`, `'pl'`, `'uk'`)
 * @param array<non-empty-string, string|int|float|\DateTimeInterface> $parameters Named ICU parameters keyed by placeholder name
 *
 * @return string Translated and nl2br-wrapped string
 *
 * @see _t() To translate to the current session locale
 * @see \PHP_SF\System\Core\Lang::getCurrentLocale()
 * @see \PHP_SF\System\Classes\Helpers\Locale For all available locale keys
 */
function _tt( string $key, string $locale,
    #[ArrayShape( [
        // Keys are dynamic — they match the {placeholder} names defined in the translation YAML.
        // The types below cover all values accepted by PHP's IntlMessageFormatter:

        // --- ICU plural rule selector ({count, plural, one {...} other {...}}) ---
        'count'  => 'int',

        // --- ICU select/gender selector ({gender, select, male {...} female {...} other {...}}) ---
        'gender' => 'string',

        // --- ICU date/time formatting ({date, date, long}) ---
        'date'   => DateTimeInterface::class,

        // --- Generic named substitution ({name}, {field}, {value}, …) ---
        'string' => 'string|int|float',
    ] )]
    array $parameters = []
): string
{
    return nl2br( Translator::getInstance()->translateTo( $key, $locale, $parameters ) );
}


function j_decode( string $json, bool $associative = false, int $depth = 512, int $flags = JSON_THROW_ON_ERROR ): mixed
{
    return json_decode( $json, $associative, $depth, $flags );
}

function j_encode( mixed $value, int $flags = JSON_THROW_ON_ERROR, int $depth = 512 ): string|false
{
    return json_encode( $value, $flags, $depth );
}


function user(): UserInterface|false
{
    return auth::user();
}


function camel_to_snake( string $input ): string
{
    return str_replace( '__', '_', strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $input ) ) );
}

function snakeToCamel( string $input ): string
{
    return lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $input ) ) ) );
}


/**
 * @noinspection GlobalVariableUsageInspection
 */
function env( string $name, string|null $default = null ): string|null
{
    if ( array_key_exists( $name, $_ENV ) === false )
        return null;

    return $_ENV[ $name ] ?? $default;
}


function project_dir(): string
{
    return Kernel::getInstance()->getProjectDir();
}
