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

namespace PHP_SF\Framework\Http\Middleware;

use App\Entity\User;
use PHP_SF\System\Classes\Abstracts\Middleware;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Router;
use Symfony\Component\HttpFoundation\JsonResponse;

class admin extends Middleware
{

    /**
     * @noinspection MethodShouldBeFinalInspection
     */
    public function result(): bool|RedirectResponse|JsonResponse
    {
        if ( auth::isAuthenticated() === false ) {
            if ( str_starts_with( Router::$currentRoute->url, '/api/' ) )
                return new JsonResponse(
                    [ 'error' => 'Unauthorized!', ], JsonResponse::HTTP_UNAUTHORIZED
                );

            return $this->redirectTo( 'admin_login_page' );
        }


        return User::isAdmin();
    }
}
