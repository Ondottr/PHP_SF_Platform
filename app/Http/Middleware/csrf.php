<?php declare( strict_types=1 );

namespace PHP_SF\Framework\Http\Middleware;

use PHP_SF\System\Classes\Abstracts\Middleware;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Router;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Global CSRF middleware — registered via Router::addGlobalMiddleware( csrf::class ).
 *
 * Automatically skips:
 *   - Non-mutating HTTP methods (GET, HEAD, OPTIONS)
 *   - Routes whose URL starts with "/api/"
 *   - Routes that include the no_csrf marker in their own middleware list
 *
 * To opt a route out, add no_csrf::class to its middleware:
 *   #[Route( url: 'webhook/stripe', httpMethod: 'POST', middleware: [ no_csrf::class ] )]
 */
final class csrf extends Middleware
{

    private const MUTATING_METHODS = [ 'POST', 'PUT', 'PATCH', 'DELETE' ];


    protected function result(): bool|RedirectResponse|JsonResponse
    {
        if ( !in_array( Router::$currentRoute->httpMethod, self::MUTATING_METHODS, true ) )
            return true;

        if ( str_starts_with( Router::$currentRoute->url, '/api/' ) )
            return true;

        if ( $this->hasMiddleware( Router::$currentRoute->middleware ?? [], no_csrf::class ) )
            return true;

        $sessionToken = s()->get( '_csrf_token' );
        $submitted    = $this->request->request->get( '_token', '' );

        if ( $sessionToken === null || !hash_equals( $sessionToken, $submitted ) )
            return $this->redirectBack( errors: [ RedirectResponse::ALERT_DANGER => 'Invalid CSRF token.' ] );

        return true;
    }


    private function hasMiddleware( mixed $middleware, string $class ): bool
    {
        if ( is_string( $middleware ) )
            return $middleware === $class;

        if ( is_array( $middleware ) ) {
            foreach ( $middleware as $key => $value ) {
                if ( is_string( $key ) && $key === $class )
                    return true;

                if ( $this->hasMiddleware( $value, $class ) )
                    return true;
            }
        }

        return false;
    }

}
