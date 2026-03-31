<?php declare( strict_types=1 );

namespace PHP_SF\Framework\Http\Middleware;

use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHP_SF\System\Classes\Abstracts\Middleware;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Interface\UserInterface;
use PHP_SF\System\Kernel;
use PHP_SF\System\Router;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Core session-based authentication middleware.
 *
 * Holds the currently authenticated user in {@see self::$user} for the lifetime of the request.
 * When placed on a route, {@see result()} allows the request through only if a valid user session
 * exists; API routes receive a 401 JSON response, all others are redirected to the login page.
 *
 * Typical usage in application code:
 * - {@see auth::logInUser()} — populate the session after a successful credential check.
 * - {@see auth::logOutUser()} — destroy the session.
 * - {@see auth::user()} — retrieve a fresh user instance during the current request.
 * - {@see auth::isAuthenticated()} — quick boolean check (useful in controllers/views).
 */
class auth extends Middleware
{

    /**
     * Holds the currently authenticated user, or {@see false} when no session is active.
     *
     * @type false|UserInterface&AbstractEntity
     */
    public static false|UserInterface $user = false;


    /**
     * Returns a fresh entity instance for the authenticated user (re-fetched from the database),
     * or {@see false} when no user is authenticated.
     */
    final public static function user(): false|UserInterface
    {
        if ( self::$user !== false ) {
            /**
             * @var UserInterface&AbstractEntity $userClass
             */
            $userClass = ( Kernel::getApplicationUserClassName() );

            return $userClass::find( self::$user->getId() );
        }

        return self::$user;
    }

    /**
     * Destroys the current session and clears the in-memory user reference.
     */
    public static function logOutUser(): void
    {
        s()->clear();

        self::$user = false;
    }


    /**
     * Runs the authentication gate.
     *
     * Returns {@see true} when a valid session exists.
     * Unauthenticated requests to {@code /api/*} routes receive a 401 JSON response;
     * all other unauthenticated requests are redirected to the named {@code login_page} route.
     */
    final public function result(): bool|RedirectResponse|JsonResponse
    {
        if ( self::isAuthenticated() === false ) {
            if ( str_starts_with( Router::$currentRoute->url, '/api/' ) )
                return new JsonResponse( [ 'error' => 'Unauthorized!' ], JsonResponse::HTTP_UNAUTHORIZED );

            return $this->redirectTo( 'login_page' );
        }

        return true;
    }


    /**
     * Establishes the authenticated user for this request.
     *
     * - Called with no argument (or {@see null}) during bootstrap: reads the user ID from the
     *   session and loads the corresponding entity into {@see self::$user}.
     * - Called with an explicit {@see UserInterface} instance after a successful credential check:
     *   stores the entity in {@see self::$user} and persists the user ID to the session.
     *
     * Silently returns when the session contains no user ID, or when the stored ID no longer
     * resolves to an existing user record.
     */
    public static function logInUser( UserInterface|null $user = null ): void
    {
        if ( $user === null ) {
            $userId = s()->get( 'session_user_id' );

            if ( $userId !== null ) {
                /**
                 * @var UserInterface&AbstractEntity $userClass
                 */
                $userClass = ( Kernel::getApplicationUserClassName() );
                $user      = $userClass::find( $userId );

                if ( $user === null )
                    return;

                self::$user = $user;
            }
        } elseif ( $user instanceof ( Kernel::getApplicationUserClassName() ) ) {
            self::$user = $user;

            s()->set( 'session_user_id', $user->getId() );
        }
    }


    /**
     * Returns {@see true} when a valid user entity is loaded into {@see self::$user}.
     */
    final public static function isAuthenticated(): bool
    {
        return self::$user instanceof ( Kernel::getApplicationUserClassName() );
    }

}
