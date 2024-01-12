<?php declare( strict_types=1 );
/*
 * Copyright © 2018-2024, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 * granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 * INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 * LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 * RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 * TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\QueryBuilder;
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
use PHP_SF\System\Database\DoctrineEntityManager;
use PHP_SF\System\Database\Redis;
use PHP_SF\System\Router;
use Predis\Client;
use Predis\Pipeline\Pipeline;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

require_once __DIR__ . '/time_functions.php';
require_once __DIR__ . '/view_functions.php';

function em(): DoctrineEntityManager
{
    return DoctrineEntityManager::getEntityManager();
}

function qb(): QueryBuilder
{
    return em()->createQueryBuilder();
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
            $link = str_replace( [ '{', '}' ], '', $link );

            // Replace parameters in route link
            foreach ( $pathParams as $propertyName => $propertyValue )
                $link = str_replace( sprintf( '$%s', $propertyName ), (string)$propertyValue, $link );
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


function _t( string $stringName, ...$values ): string
{
    return nl2br( Translator::getInstance()->translate( $stringName, ...$values ) );
}

function _tr( array|object $arr, string|null $localeName = null, string|null $localeKey = null ): string|array|object {
    $translatedValue = Translator::getInstance()->translateFromArray( $arr, $localeName, $localeKey );

    if ( is_scalar( $translatedValue ) )
        return nl2br( $translatedValue );

    return $translatedValue;
}


function j_decode( string $json, bool $associative = false, int $depth = 512, int $flags = JSON_THROW_ON_ERROR ): mixed
{
    return json_decode( $json, $associative, $depth, $flags );
}

function j_encode( mixed $value, int $flags = JSON_THROW_ON_ERROR, int $depth = 512 ): string|false
{
    return json_encode( $value, $flags, $depth );
}


function user(): User|false
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


function project_dir( string $path = '' ): string
{
    return Kernel::getInstance()->getProjectDir();
}