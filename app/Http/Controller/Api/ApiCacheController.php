<?php /** @noinspection MethodShouldBeFinalInspection @noinspection PhpUnused */
declare( strict_types=1 );

/*
 * Copyright © 2018-2023, Nations Original Sp. z o.o. <contact@nations-original.com>
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

use Memcached;
use PHP_SF\Framework\Http\Middleware\admin;
use PHP_SF\Framework\Http\Middleware\api;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;


class ApiCacheController extends AbstractController
{

    #[Route( url: 'api/cache_clear/all', httpMethod: 'GET', middleware: [ api::class, admin::class ] )]
    public function api_clear_all_cache(): JsonResponse
    {
        if( function_exists( 'apcu_enabled' ) && apcu_enabled() )
            aca()->clear();
        
        rca()->clear();
        if ( class_exists( Memcached::class ) )
            mca()->clear();

        return new JsonResponse( [ 'status' => 'ok' ], JsonResponse::HTTP_OK );
    }

}
