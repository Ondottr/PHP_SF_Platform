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

namespace PHP_SF\Framework\Http\Controller\Defaults;

use App\Kernel;
use JetBrains\PhpStorm\NoReturn;
use PHP_SF\Framework\Http\Middleware\auth;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Router;
use PHP_SF\Templates\base;


final class DefaultController extends AbstractController
{

    #[Route( url: 'base', httpMethod: 'GET' )]
    public function base(): Response
    {
        return $this->render( base::class );
    }

    #[Route( url: 'welcome', httpMethod: 'GET', middleware: auth::class )]
    public function welcome_page(): Response
    {
        return $this->render( base::class );
    }


    /** @noinspection ForgottenDebugOutputInspection */
    #[NoReturn]
    #[Route( url: 'api/routes_list', httpMethod: 'GET' )]
    final public function api_routes_list(): void
    {
        dd(
            array_merge(
                Router::getRoutesList(),
                Kernel::getInstance()
                    ->getContainer()
                    ->get( 'router' )
                    ?->getRouteCollection()
                    ?->all() ?? []
            )
        );
    }

}
