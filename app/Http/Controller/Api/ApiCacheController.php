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

namespace PHP_SF\Framework\Http\Controller\Api;

use PHP_SF\System\Attributes\Route;
use PHP_SF\Framework\Http\Middleware\api;
use PHP_SF\Framework\Http\Middleware\admin;
use Symfony\Component\HttpFoundation\JsonResponse;
use PHP_SF\System\Classes\Abstracts\AbstractController;


class ApiCacheController extends AbstractController
{

    #[Route( url: 'api/cache_clear/translations', httpMethod: 'GET', middleware: [ api::class, admin::class ] )]
    public function clear_translations_cache(): JsonResponse
    {
        $keys = rc()->keys( SERVER_NAME . ':cache:translated_strings:*' );

        foreach ( $keys as $key )
            rp()->del( $key );

        return new JsonResponse( [ 'status' => 'ok' ], JsonResponse::HTTP_OK );
    }

    #[Route( url: 'api/cache_clear/routes', httpMethod: 'GET', middleware: [ api::class, admin::class ] )]
    public function clear_router_cache(): JsonResponse
    {
        rp()->del( [
                       SERVER_NAME . ':cache:routes_list',
                       SERVER_NAME . ':cache:routes_by_url_list',
                   ] );

        return new JsonResponse( [ 'status' => 'ok' ], JsonResponse::HTTP_OK );
    }

    #[Route( url: 'api/cache_clear/all', httpMethod: 'GET', middleware: [ api::class, admin::class ] )]
    public function clear_all_cache(): JsonResponse
    {
        $keys = rc()->keys( SERVER_NAME . ':cache:*' );

        foreach ( $keys as $key )
            rp()->del( $key );

        return new JsonResponse( [ 'status' => 'ok' ], JsonResponse::HTTP_OK );
    }

    #[Route( url: 'api/cache_clear/templates', httpMethod: 'GET', middleware: [ api::class, admin::class ] )]
    public function clear_templates_cache(): JsonResponse
    {
        $dir = sprintf( '/tmp/%s/PHP_SF/CachedTemplates', SERVER_NAME );

        if ( file_exists( $dir ) && is_dir( $dir ) )
            exec( sprintf( 'rm -rf %s', escapeshellarg( $dir ) ) );

        return new JsonResponse( [ 'status' => 'ok' ], JsonResponse::HTTP_OK );
    }

    #[Route( url: 'api/admin_panel/cache_clear/apcu', httpMethod: 'GET', middleware: [ admin::class, api::class ] )]
    public function clear_apcu_cache(): JsonResponse
    {
        apcu_clear_cache();

        return new JsonResponse( [ 'status' => 'ok' ], JsonResponse::HTTP_OK );
    }

}
