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
 * Provide a custom logic to determine if the middleware must be executed or not <p>
 * Example: <p>
 * \#[{@link Route}( middleware: [ {@link MiddlewareAll::class} => [ {@link auth::class} ], {@link MiddlewareAny::class} => [ {@link admin::class}, {@link api::class} ] ] )]
 *
 * In this example, the route will be accepted if:
 * - The user is authenticated and the user is an admin
 * - The user is authenticated and the request is an API request
 *
 * Note: <p>
 * See the description of {@link MiddlewareAll::class} and {@link MiddlewareAny::class} for more information about the execution logic
 * First the {@link MiddlewareAll::class} will be executed, then the {@link MiddlewareAny::class} will be executed
 * regardless of the position of the {@link Middleware::class} constants in the array <p>
 *
 * Another example: <p>
 * \#[{@link Route}( middleware: [ {@link MiddlewareAll::class} => [ ... ], {@link MiddlewareAny::class} => [ ... ] ] )] <p>
 * In the example below, the execution logic will be the same as the example above (First {@link MiddlewareAll::class}, then {@link MiddlewareAny::class}) <p>
 * \#[{@link Route}( middleware: [ {@link MiddlewareAny::class} => [ ... ], {@link MiddlewareAll::class} => [ ... ] ] )]
 */
final class MiddlewareCustom extends MiddlewareType
{

    /**
     * @throws RouteMiddlewareException If any of validation fails it is recommended to throw an {@link RouteMiddlewareException}
     */
    public function validate(): MiddlewareType
    {
        $middlewares = $this->middlewares[ self::class ];

        // Check if middleware array is not empty
        if ( empty( $this->middlewares ) || empty( $middlewares ) )
            throw new RouteMiddlewareException( self::class . ' array must not be empty!' );

        // Check if middleware array contains only arrays with max 2 elements
        foreach ( $this->middlewares as $middleware )
            if ( count( $middleware ) > 2 )
                throw new RouteMiddlewareException( self::class . ' array must contain only arrays with max 2 elements!' );

        // Check if middleware array contains only arrays with max 2 elements and all elements are arrays
        foreach ( $this->middlewares as $middleware )
            foreach ( $middleware as $element )
                if ( is_array( $element ) === false )
                    throw new RouteMiddlewareException( self::class . ' array must contain only arrays with max 2 elements and all elements must be arrays!' );

        foreach ( $this->middlewares as $middleware ) {
            foreach ( $middleware as $key => $arrOfMiddles ) {
                if ( $key !== MiddlewareAll::class && $key !== MiddlewareAny::class )
                    throw new RouteMiddlewareException(
                        self::class . ' array must contain only arrays with max 2 elements and all keys must be ' . MiddlewareAll::class . ' or ' . MiddlewareAny::class
                    );

                foreach ( $arrOfMiddles as $string ) {
                    if ( is_string( $string ) === false ||
                        is_subclass_of( $string, Middleware::class ) === false
                    )
                        throw new RouteMiddlewareException(
                            self::class . ' array must contain only arrays with max 2 elements and all elements strings of valid classes extended from ' . Middleware::class
                        );
                }
            }
        }


        return $this;
    }

    /**
     * Return `true` if the middleware allow the route to be executed, or a {@link RedirectResponse} or {@link JsonResponse}
     * which will be returned by the {@link Middleware::execute()} method
     *
     * @return bool|RedirectResponse|JsonResponse Result of the {@link Middleware::execute()} method
     */
    public function execute(): bool|RedirectResponse|JsonResponse
    {
        $middlewares = $this->middlewares[ self::class ];

        if ( array_key_exists( MiddlewareAll::class, $middlewares ) ) {
            $all = new MiddlewaresExecutor( [ MiddlewareAll::class => $middlewares[ MiddlewareAll::class ] ],
                $this->request, $this->kernel, $this->controller
            );

            $result = $all->execute();

            if ( $result !== true )
                return $result;

        }

        if ( array_key_exists( MiddlewareAny::class, $middlewares ) ) {
            $any = new MiddlewaresExecutor( [ MiddlewareAny::class => $middlewares[ MiddlewareAny::class ] ],
                $this->request, $this->kernel, $this->controller
            );

            $result = $any->execute();

            if ( $result !== true )
                return $result;

        }

        return true;
    }

}