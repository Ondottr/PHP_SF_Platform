<?php declare( strict_types=1 );
/**
 * Created by PhpStorm.
 * User: ondottr
 * Date: 15/02/2023
 * Time: 8:13 am
 */

namespace PHP_SF\System\Classes\MiddlewareChecks;

use PHP_SF\System\Classes\Abstracts\MiddlewareType;
use PHP_SF\System\Classes\Exception\RouteMiddlewareException;
use PHP_SF\System\Core\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * MiddlewareAny: At least one middleware must be executed and return true <p>
 * Example: <p>
 * \#[{@link Route}( middleware: [ {@link MiddlewareAny::class} => [ {@link auth::class}, {@link api::class} ] ] )]
 *
 * In this example, the route will be accepted if:
 * - The user is authenticated
 * - The request is an API request
 *
 * Note: <p>
 * If all middlewares return false, the execution will be stopped, route will be rejected,
 * and result of the last middleware will be returned
 */
final class MiddlewareAny extends MiddlewareType
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
     * Return `true` if at least one middleware provided in the {@link Route} annotation with key {@link MiddlewareAny::class} returns `true` <p>
     * Or returns {@link RedirectResponse} or {@link JsonResponse} from {@link Middleware::execute()} method when all middlewares return false
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
            if ( $mResult === true )
                return true;

        }

        return $mResult;
    }

}