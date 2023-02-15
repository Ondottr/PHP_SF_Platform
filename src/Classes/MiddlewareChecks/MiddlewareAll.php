<?php declare( strict_types=1 );
/**
 * Created by PhpStorm.
 * User: ondottr
 * Date: 15/02/2023
 * Time: 8:13 am
 */

namespace PHP_SF\System\Classes\MiddlewareChecks;

use PHP_SF\System\Classes\Abstracts\Middleware;
use PHP_SF\System\Classes\Abstracts\MiddlewareType;
use PHP_SF\System\Classes\Exception\RouteMiddlewareException;
use PHP_SF\System\Core\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * MiddlewareAll: All middlewares must be executed and return true <p>
 * Example: <p>
 * \#[{@link Route}( middleware: [ {@link MiddlewareAll::class} => [ {@link auth::class}, {@link api::class} ] ] )]
 *
 * In this example, the route will be accepted only if:
 * - The user is authenticated and the request is an API request
 *
 * Note: <p>
 * On the first middleware that returns false, the execution will be stopped, route will be rejected,
 * and the middleware result will be returned
 */
final class MiddlewareAll extends MiddlewareType
{

    /**
     * @throws RouteMiddlewareException If any of validation fails it is recommended to throw an {@link RouteMiddlewareException}
     */
    public function validate(): self
    {
        $middlewares = $this->middlewares[ self::class ];

        // Check if middleware array is not empty
        if ( empty( $middlewares ) )
            throw new RouteMiddlewareException( self::class . ' array must not be empty!' );

        // Check if middleware array contains only strings
        if ( is_array( $middlewares ) === false )
            throw new RouteMiddlewareException( self::class . ' array must contain only arrays with strings!' );

        // Check if middleware array contains unique values only
        if ( count( array_unique( $middlewares ) ) !== count( $middlewares ) )
            throw new RouteMiddlewareException( self::class . ' array must contain unique values only!' );

        // Check if middleware array contains only strings
        if ( array_filter( $middlewares, fn( $middleware ) => is_string( $middleware ) ) !== $middlewares )
            throw new RouteMiddlewareException( self::class . ' array must contain only strings!' );

        return $this;
    }

    /**
     * Return `true` if the all middlewares provided in the {@link Route} annotation with key {@link MiddlewareAll::class} return `true` <p>
     * Or returns {@link RedirectResponse} or {@link JsonResponse} from {@link Middleware::execute()} method when any middleware returns false
     *
     * @return bool|RedirectResponse|JsonResponse Result of the {@link Middleware::execute()} method
     */
    public function execute(): bool|RedirectResponse|JsonResponse
    {
        $middlewares = array_values( $this->middlewares )[0];

        if ( empty( $middlewares ) )
            return true;

        // Loop through each middleware to be executed and check its result.
        foreach ( $middlewares as $middleware ) {
            // Instantiate the middleware class
            $mResult =
                ( new $middleware( $this->request, $this->kernel, $this->controller ) )
                    ->execute();

            // If the middleware result is not true, send the result and exit.
            if ( $mResult !== true )
                return $mResult;

        }

        return true;
    }

}