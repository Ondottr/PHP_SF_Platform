<?php declare( strict_types=1 );

/*
 * Copyright © 2018-2022, Nations Original Sp. z o.o. <contact@nations-original.com>
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

use App\Kernel;
use Predis\Client;
use App\Entity\User;
use PHP_SF\System\Router;
use Predis\Pipeline\Pipeline;
use Doctrine\ORM\QueryBuilder;
use PHP_SF\System\Core\Sessions;
use PHP_SF\System\Database\Query;
use PHP_SF\System\Database\Redis;
use PHP_SF\System\Core\Translator;
use PHP_SF\System\Core\TemplatesCache;
use PHP_SF\System\Classes\Helpers\Curl;
use PHP_SF\Framework\Http\Middleware\auth;
use PHP_SF\System\Database\DoctrineEntityManager;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use PHP_SF\System\Classes\Exception\RouteParameterExpectedException;

require_once __DIR__ . '/time_functions.php';
require_once __DIR__ . '/view_functions.php';

function em( bool $cacheEnabled = true ): DoctrineEntityManager
{
    return DoctrineEntityManager::getEntityManager( $cacheEnabled );
}

function qb( int $cacheType = Query::CACHE_TYPE_PERSISTENT ): QueryBuilder
{
    Query::setCurrentCacheType( $cacheType );

    return em()->createQueryBuilder();
}

function rc(): Client
{
    return Redis::getRc();
}

function rp(): Pipeline
{
    return Redis::getRp();
}

function s(): Session
{
    return Sessions::getInstance();
}

function routeLink( string $routeName, array $with = [], array $query = [], string $siteUrl = null ): string
{
    try {
        if ( Router::isRouteExists( $routeName ) === false )
            return Kernel::getInstance()->getContainer()->get( 'router' )?->generate( $routeName, $with );

        throw new RouteNotFoundException;

    } catch ( RouteNotFoundException ) {
        if($siteUrl !== null)
            $link = $siteUrl . Router::getRouteLink( $routeName );
        else
            $link = Router::getRouteLink( $routeName );

        if ( !empty( $with ) ) {
            $link = str_replace( [ '{$', '}' ], '', $link );

            foreach ( $with as $propertyName => $propertyValue )
                $link = str_replace( $propertyName, (string)$propertyValue, $link );

        }

        if ( Router::isRouteExists( $routeName ) ) {
            $routeInfo = Router::getRouteInfo( $routeName );

            if ( empty( $routeInfo['routeParams'] ) === false )
                foreach ( $routeInfo['routeParams'] as $param )
                    if ( array_key_exists( $param, $with ) === false )
                        throw new RouteParameterExpectedException(
                            $routeName, $param
                        );
        }

        if ( str_contains( $link, '{$' ) )
            return "#$routeName";

        if ( !empty( $query ) ) {
            $link .= '?';
            $i = 0;
            $arrCount = count( $query );
            foreach ( $query as $key => $value ) {
                $i++;
                $link .= "$key=$value";

                if ( $i < $arrCount )
                    $link .= '&';
            }
        }

        return $link;
    }
}

function _t( string $stringName, ...$values ): string
{
    return nl2br( Translator::getInstance()->translate( $stringName, ...$values ) );
}

function tc(): TemplatesCache
{
    return TemplatesCache::getInstance();
}

function method( string $className, string $name ): ReflectionMethod
{
    return ( new ReflectionClass( $className ) )
        ->getMethod( $name );
}


/**
 * @return Curl
 */
function curl(): Curl
{
    return Curl::getInstance();
}

/**
 * @param string $json
 * @param bool   $associative
 * @param int    $depth
 * @param int    $flags
 *
 * @return mixed
 */
function j_decode( string $json, bool $associative = false, int $depth = 512, int $flags = JSON_THROW_ON_ERROR ): mixed
{
    return json_decode( $json, $associative, $depth, $flags );
}

/**
 * @param mixed $value
 * @param int   $flags
 * @param int   $depth
 *
 * @return string|false
 */
function j_encode( mixed $value, int $flags = JSON_THROW_ON_ERROR, int $depth = 512 ): string|false
{
    return json_encode( $value, $flags, $depth );
}

/**
 * @param bool $cacheEnabled
 *
 * @return User|false
 */
function user( bool $cacheEnabled = true ): User|false
{
    return auth::user( $cacheEnabled );
}

/**
 * @param string $input
 *
 * @return string
 */
function camel_to_snake( string $input ): string
{
    return str_replace( '__', '_', strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $input ) ) );
}

/**
 * @param string $input
 *
 * @return string
 */
function snakeToCamel( string $input ): string
{
    return lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $input ) ) ) );
}

/**
 * @param string $name
 *
 * @return string|null
 */
function env( string $name ): ?string
{
    if ( !array_key_exists( $name, $_ENV ) )
        return null;

    return $_ENV[ $name ];
}
