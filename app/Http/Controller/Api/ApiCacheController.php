<?php /** @noinspection MethodShouldBeFinalInspection @noinspection PhpUnused */
declare( strict_types=1 );

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

namespace PHP_SF\Framework\Http\Controller\Api;

use PHP_SF\Framework\Http\Middleware\admin;
use PHP_SF\Framework\Http\Middleware\api;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;


class ApiCacheController extends AbstractController
{

    #[Route( url: 'api/cache_clear/translations', httpMethod: 'GET', middleware: [ api::class, admin::class ] )]
    public function api_clear_translations_cache(): JsonResponse
    {
        $keys = rc()->keys( 'translated_strings:*' );

        foreach ( $keys as $key )
            rp()->del( $key );

        return new JsonResponse( [ 'status' => 'ok' ], JsonResponse::HTTP_OK );
    }

    #[Route( url: 'api/cache_clear/routes', httpMethod: 'GET', middleware: [ api::class, admin::class ] )]
    public function api_clear_router_cache(): JsonResponse
    {
        rp()->del( ['routes_list', 'routes_by_url_list' ] );

        return new JsonResponse( [ 'status' => 'ok' ], JsonResponse::HTTP_OK );
    }

    #[Route( url: 'api/cache_clear/all', httpMethod: 'GET', middleware: [ api::class, admin::class ] )]
    public function api_clear_all_cache(): JsonResponse
    {
        $keys = rc()->keys( env( 'SERVER_PREFIX' ) . ':cache:*' );

        foreach ( $keys as $key )
            rp()->del( $key );

        return new JsonResponse( [ 'status' => 'ok' ], JsonResponse::HTTP_OK );
    }

    #[Route( url: 'api/cache_clear/templates', httpMethod: 'GET', middleware: [ api::class, admin::class ] )]
    public function api_clear_templates_cache(): JsonResponse
    {
        $dir = sprintf( '/tmp/%s/PHP_SF/CachedTemplates', env( 'SERVER_PREFIX' ) );

        if ( file_exists( $dir ) && is_dir( $dir ) )
            exec( sprintf( 'rm -rf %s', escapeshellarg( $dir ) ) );

        return new JsonResponse( [ 'status' => 'ok' ], JsonResponse::HTTP_OK );
    }

    #[Route( url: 'api/admin_panel/cache_clear/apcu', httpMethod: 'GET', middleware: [ admin::class, api::class ] )]
    public function api_clear_apcu_cache(): JsonResponse
    {
        if ( function_exists( 'apcu_clear_cache' ) )
            apcu_clear_cache();

        return new JsonResponse( [ 'status' => 'ok' ], JsonResponse::HTTP_OK );
    }

}
