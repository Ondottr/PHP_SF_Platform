<?php declare(strict_types=1);

use App\Kernel;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use PHP_SF\Framework\Http\Middleware\auth;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Exception\RouteParameterExpectedException;
use PHP_SF\System\Classes\Helpers\StringCase;
use PHP_SF\System\Core\Sessions;
use PHP_SF\System\Core\TranslatorV2;
use PHP_SF\System\Interface\UserInterface;
use PHP_SF\System\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

function em(string $connectionName): EntityManager
{
    $kernel = Kernel::getInstance();

    return $kernel->getContainer()->get('doctrine.orm.' . $connectionName . '_entity_manager');
}

function qb(string $connectionName): QueryBuilder
{
    return em($connectionName)->createQueryBuilder();
}

function s(): Session
{
    return Sessions::getInstance();
}

function r(): Request
{
    return Router::getRequest();
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
 * @throws InvalidArgumentException                 If $siteUrl is not valid url
 * @throws RouteNotFoundException                   If route not exists in symfony routes and in routes from {@link Route} attribute
 * @throws RouteParameterExpectedException          If route parameter is not provided in $with array and is required
 * @throws Psr\SimpleCache\InvalidArgumentException If cache key is invalid
 */
function routeLink(string $routeName, array $pathParams = [], array $queryParams = [], ?string $siteUrl = null): string
{
    $cacheKey = sprintf(
        'route_link_%s_%s_%s_%s',
        $routeName,
        md5(serialize($pathParams)),
        md5(serialize($queryParams)),
        md5(serialize($siteUrl)),
    );

    // Check if route link is cached
    if (ca()->has($cacheKey)) {
        // Return cached route link
        return ca()->get($cacheKey);
    }

    if (null !== $siteUrl && false === filter_var($siteUrl, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Invalid site url');
    }

    /**
     * First check if route exists
     * If not then try to generate link from symfony routes
     * If symfony route not exists too, then throw an exception.
     */
    try {
        if (false === Router::isRouteExists($routeName)) {
            $link = Kernel::getInstance()->getContainer()->get('router')?->generate($routeName, $pathParams);

            // If link is not null then cache it and return
            if (null !== $link) {
                ca()->set($cacheKey, $link);

                return $link;
            }
        }

        // If route not exists in symfony routes and in routes from {@link Route} attribute
        throw new RouteNotFoundException();
    } catch (RouteNotFoundException) {
        // Get route link
        $link = (null !== $siteUrl)
            ? $siteUrl . Router::getRouteLink($routeName)
            : Router::getRouteLink($routeName);

        // Check if parameters are provided
        if (false === empty($pathParams)) {
            // Replace parameters in route link
            foreach ($pathParams as $propertyName => $propertyValue) {
                $link = str_replace(sprintf('{%s}', $propertyName), (string) $propertyValue, $link);
            }
        }

        // Check if route exists in routes from {@link Route} attribute
        if (Router::isRouteExists($routeName)) {
            // Get route info
            $routeInfo = Router::getRouteInfo($routeName);

            // Check if route contains parameters
            if (false === empty($routeInfo['routeParams'])) {
                // Loop through route parameters
                foreach ($routeInfo['routeParams'] as $param) {
                    // Check if parameter is provided to replace in route link
                    if (false === array_key_exists($param, $pathParams)) {
                        // If parameter is required then throw an exception
                        throw new RouteParameterExpectedException($routeName, $param);
                    }
                }
            }
        }

        // Check if route link contains not replaces parameters
        // This means that route with this name not exists in routes from {@link Route} attribute
        if (strpos($link, '{$')) {
            // Return route name
            return "#$routeName";
        }

        // Check if query parameters are provided
        if (false === empty($queryParams)) {
            // Add query parameters to route link
            $link .= '?' . http_build_query($queryParams);
        }

        // Cache route link
        ca()->set($cacheKey, $link);

        // Return route link
        return $link;
    }
}

function _t(string $id, array $parameters = []): string
{
    return nl2br(TranslatorV2::getInstance()->trans($id, $parameters));
}

function _tt(string $id, string $translateTo, array $parameters = []): string
{
    return nl2br(TranslatorV2::getInstance()->trans($id, $parameters, null, $translateTo));
}

function j_decode(string $json, bool $associative = false, int $depth = 512, int $flags = JSON_THROW_ON_ERROR): mixed
{
    return json_decode($json, $associative, $depth, $flags);
}

function j_encode(mixed $value, int $flags = JSON_THROW_ON_ERROR, int $depth = 512): string|false
{
    return json_encode($value, $flags, $depth);
}

function user(): UserInterface|false
{
    return auth::user();
}

/**
 * @deprecated use {@see string_to_snake()} instead; this method will be removed in v3
 */
#[Deprecated(message: 'Use string_to_snake() instead; this method will be removed in v3.', since: '2.2.0')]
function camel_to_snake(string $input): string
{
    return string_to_snake($input);
}

/**
 * Converts any string format to snake_case.
 *
 * Recognises camelCase, PascalCase, SCREAMING_SNAKE, kebab-case, dot.notation,
 * slash/separated, mixed separators, acronyms (XMLHttpRequest → xml_http_request),
 * and digit→uppercase boundaries (user1Profile → user1_profile).
 * Unicode letters are preserved; accents are not stripped.
 * Leading/trailing separators and whitespace are ignored.
 *
 * @param string $input any string — empty string returns empty string
 *
 * @return string lowercase words joined by underscores
 *
 * @example string_to_snake('helloWorld')     // 'hello_world'
 * @example string_to_snake('XMLHttpRequest') // 'xml_http_request'
 * @example string_to_snake('user1Profile')   // 'user1_profile'
 */
function string_to_snake(string $input): string
{
    return StringCase::snake($input);
}

/**
 * Converts any string format to SCREAMING_SNAKE_CASE.
 *
 * Applies the same tokenisation as {@see string_to_snake()}, then uppercases the result.
 *
 * @param string $input any string — empty string returns empty string
 *
 * @return string uppercase words joined by underscores
 *
 * @example string_to_screaming_snake('helloWorld')     // 'HELLO_WORLD'
 * @example string_to_screaming_snake('XMLHttpRequest') // 'XML_HTTP_REQUEST'
 */
function string_to_screaming_snake(string $input): string
{
    return StringCase::screamingSnake($input);
}

/**
 * @deprecated use {@see string_to_camel()} instead; this method will be removed in v3
 */
#[Deprecated(message: 'Use camel_to_snake() instead; this method will be removed in v3.', since: '2.2.0')]
function snakeToCamel(string $input): string
{
    return string_to_camel($input);
}

/**
 * Converts any string format to camelCase.
 *
 * Applies the same tokenisation as {@see string_to_snake()}, then joins words
 * with the first word lowercase and every subsequent word title-cased.
 * Note: {@see mb_convert_case()} with MB_CASE_TITLE treats digits as word
 * boundaries, so digits in suffix words capitalise the following letter
 * (e.g. 'hello2world' → 'hello2World' when not the first word).
 *
 * @param string $input any string — empty string returns empty string
 *
 * @return string words joined without separator, first word lowercase, rest title-cased
 *
 * @example string_to_camel('hello_world')    // 'helloWorld'
 * @example string_to_camel('XMLHttpRequest') // 'xmlHttpRequest'
 * @example string_to_camel('version2API')    // 'version2Api'
 */
function string_to_camel(string $input): string
{
    return StringCase::camel($input);
}

/**
 * Converts any string format to PascalCase.
 *
 * Applies the same tokenisation as {@see string_to_snake()}, then joins words
 * with every word title-cased (including the first).
 * Note: {@see mb_convert_case()} with MB_CASE_TITLE treats digits as word
 * boundaries, so digits capitalise the following letter
 * (e.g. 'hello2world' → 'Hello2World').
 *
 * @param string $input any string — empty string returns empty string
 *
 * @return string words joined without separator, every word title-cased
 *
 * @example string_to_pascal('hello_world')    // 'HelloWorld'
 * @example string_to_pascal('XMLHttpRequest') // 'XmlHttpRequest'
 * @example string_to_pascal('version2API')    // 'Version2Api'
 */
function string_to_pascal(string $input): string
{
    return StringCase::pascal($input);
}

/**
 * Converts any string format to kebab-case.
 *
 * Applies the same tokenisation as {@see string_to_snake()}, then joins words
 * with hyphens instead of underscores.
 *
 * @param string $input any string — empty string returns empty string
 *
 * @return string lowercase words joined by hyphens
 *
 * @example string_to_kebab('helloWorld')     // 'hello-world'
 * @example string_to_kebab('XMLHttpRequest') // 'xml-http-request'
 * @example string_to_kebab('user1Profile')   // 'user1-profile'
 */
function string_to_kebab(string $input): string
{
    return StringCase::kebab($input);
}

/**
 * @noinspection GlobalVariableUsageInspection
 */
function env(string $name, ?string $default = null): ?string
{
    if (false === array_key_exists($name, $_ENV)) {
        return null;
    }

    return $_ENV[$name] ?? $default;
}

function project_dir(): string
{
    return Kernel::getInstance()->getProjectDir();
}
