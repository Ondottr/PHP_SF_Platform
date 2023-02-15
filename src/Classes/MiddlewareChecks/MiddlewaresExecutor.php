<?php declare( strict_types=1 );
/**
 * Created by PhpStorm.
 * User: ondottr
 * Date: 15/02/2023
 * Time: 9:10 am
 */

namespace PHP_SF\System\Classes\MiddlewareChecks;

use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Classes\Abstracts\MiddlewareType;
use PHP_SF\System\Classes\Exception\RouteMiddlewareException;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Kernel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final class MiddlewaresExecutor
{

    public function __construct(
        private string|array $middlewares,
        private readonly Request $request,
        private readonly Kernel $kernel,
        private readonly AbstractController $controller,
    ) {}


    final public function execute(): bool|RedirectResponse|JsonResponse
    {
        if ( is_string( $this->getMiddlewares() ) && empty( $this->getMiddlewares() ) )
            throw new RouteMiddlewareException( 'Middleware must be a non-empty string' );

        if ( $this->getMiddlewares() === [] )
            return true;

        // If the middleware is a string, convert it to an array
        if ( is_string( $this->getMiddlewares() ) )
            $this->setMiddlewares( [ MiddlewareType::DEFAULT => [ $this->getMiddlewares() ] ] );

        // If the first key in the middleware array is numeric, assume it is an array of middlewares for all route matches
        if ( is_numeric( array_key_first( $this->getMiddlewares() ) ) )
            $this->setMiddlewares( [ MiddlewareType::DEFAULT => array_values( $this->getMiddlewares() ) ] );

        // First level of an array must contain only one key
        if ( count( $this->getMiddlewares() ) !== 1 )
            throw new RouteMiddlewareException( 'First level of an array must contain only one key!' );

        // Check if that first key is a valid class which extends MiddlewareCheck
        $middlewareType = array_key_first( $this->getMiddlewares() );
        if ( class_exists( $middlewareType ) === false || is_subclass_of( $middlewareType, MiddlewareType::class ) === false )
            throw new RouteMiddlewareException(
                'Middleware array keys must be a valid class which extends MiddlewareCheck!'
            );

        try {
            $result =
                ( new $middlewareType( $this->getMiddlewares(), $this->request, $this->kernel, $this->controller ) )
                    ->validate()
                    ->execute();
        } catch ( Throwable $e ) {
            throw new RouteMiddlewareException( $e->getMessage(), $e->getCode(), $e );
        }

        return $result;
    }

    private function getMiddlewares(): array|string
    {
        return $this->middlewares;
    }

    private function setMiddlewares( array|string $middleware ): void
    {
        $this->middlewares = $middleware;
    }

}