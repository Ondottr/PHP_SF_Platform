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
use PHP_SF\System\Classes\Abstracts\Middleware;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Interface\UserInterface;
use PHP_SF\System\Kernel;
use PHP_SF\System\Router;
use Symfony\Component\HttpFoundation\JsonResponse;

class auth extends Middleware
{

    public static false|UserInterface $user = false;


    final public static function user(): bool|UserInterface
    {
        if ( self::$user !== false )
            return em()
                ->getRepository( User::class )
                ->find( self::$user->getId() );

        return self::$user;
    }


    /**
     * @return bool|RedirectResponse|JsonResponse
     */
    final public function result(): bool|RedirectResponse|JsonResponse
    {
        self::logInUser();

        if ( !self::isAuthenticated() ) {
            if ( str_starts_with( Router::$currentRoute->url, '/api/' ) )
                return new JsonResponse( [ 'error' => 'Unauthorized!' ], JsonResponse::HTTP_UNAUTHORIZED );

            return $this->redirectTo( 'login_page' );
        }

        return true;
    }


    public static function logInUser( UserInterface|null $user = null ): void
    {
        if ( $user === null ) {
            $userId = s()->get( 'session_user_id' );

            if ( $userId !== null ) {
                $user = ( em()
                    ->getRepository( Kernel::getApplicationUserClassName() ) )
                    ->find( $userId );

                if ( $user === null )
                    return;

                self::$user = $user;
            }
        }
        elseif ( $user instanceof ( Kernel::getApplicationUserClassName() ) ) {
            self::$user = $user;

            s()->set( 'session_user_id', $user->getId() );
        }
    }


    final public static function isAuthenticated(): bool
    {
        return self::$user instanceof ( Kernel::getApplicationUserClassName() );
    }
}
