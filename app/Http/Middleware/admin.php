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

namespace PHP_SF\Framework\Http\Middleware;

use App\Entity\User;
use App\View\AdminPanel\Layout\Footer\admin_panel_footer;
use App\View\AdminPanel\Layout\Header\admin_panel_header;
use PHP_SF\System\Classes\Abstracts\Middleware;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Kernel;
use PHP_SF\System\Router;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class admin extends Middleware
{

    public function __construct(Request|null $request, Kernel $kernel )
    {
        $this->changeHeaderTemplateClassName( admin_panel_header::class );
        $this->changeFooterTemplateClassName( admin_panel_footer::class );

        parent::__construct( $request, $kernel );
    }

    public function result(): bool|RedirectResponse|JsonResponse
    {
        auth::logInUser();

        if ( auth::isAuthenticated() === false ) {
            if ( str_starts_with( Router::$currentRoute->url, '/api/' ) ) {
                return new JsonResponse(
                    [ 'error' => 'Unauthorized!', ], JsonResponse::HTTP_UNAUTHORIZED
                );
            }

            return $this->redirectTo( 'admin_panel_login_page' );
        }


        return User::isAdmin();
    }
}
