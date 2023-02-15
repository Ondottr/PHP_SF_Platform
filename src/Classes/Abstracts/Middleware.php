<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use ArgumentCountError;
use PHP_SF\Framework\Http\Middleware\admin;
use PHP_SF\Framework\Http\Middleware\api;
use PHP_SF\Framework\Http\Middleware\auth;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Core\MiddlewareEventDispatcher;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Interface\EventSubscriberInterface;
use PHP_SF\System\Kernel;
use PHP_SF\System\Router;
use PHP_SF\System\Traits\RedirectTrait;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use function array_key_exists;

abstract class Middleware implements EventSubscriberInterface
{
    use RedirectTrait;


    /**
     * All middlewares must be executed and at least one must return true <p>
     * Example: <p>
     * \#[{@link Route}( middleware: [ {@link Middleware::MATCH_ANY} => [ {@link auth::class}, {@link api::class} ] ] )]
     *
     * In this example, the route will be accepted if:
     * - The user is authenticated
     * - The request is an API request
     *
     * Note: <p>
     * If all middlewares return false, the execution will be stopped, route will be rejected,
     * and result of the last middleware will be returned
     */
    const MATCH_ANY = 'any';

    /**
     * Provide a custom logic to determine if the middleware must be executed or not <p>
     * Example: <p>
     * \#[{@link Route}( middleware: [ {@link Middleware::MATCH_ALL} => [ {@link auth::class} ], {@link Middleware::MATCH_ANY} => [ {@link admin::class}, {@link api::class} ] ] )]
     *
     * In this example, the route will be accepted if:
     * - The user is authenticated and the user is an admin
     * - The user is authenticated and the request is an API request
     *
     * Note: <p>
     * See the description of {@link Middleware::MATCH_ALL} and {@link Middleware::MATCH_ANY} for more information about the execution logic
     * First the {@link Middleware::MATCH_ALL} will be executed, then the {@link Middleware::MATCH_ANY} will be executed
     * regardless of the position of the {@link Middleware::class} constants in the array <p>
     *
     * Another example: <p>
     * \#[{@link Route}( middleware: [ {@link Middleware::MATCH_ALL} => [ ... ], {@link Middleware::MATCH_ANY} => [ ... ] ] )] <p>
     * In the example below, the execution logic will be the same as the example above (First {@link Middleware::MATCH_ALL}, then {@link Middleware::MATCH_ANY}) <p>
     * \#[{@link Route}( middleware: [ {@link Middleware::MATCH_ANY} => [ ... ], {@link Middleware::MATCH_ALL} => [ ... ] ] )]
     */
    const MATCH_CUSTOM = 'custom';


    public function __construct(
        protected readonly Request|null $request,
        private readonly Kernel $kernel,
        AbstractController $controller,
    )
    {
        new MiddlewareEventDispatcher( $this, $this->request, $controller );
    }


    abstract protected function result(): bool|JsonResponse|RedirectResponse;

    final public function execute(): bool|JsonResponse|RedirectResponse
    {
        if ( ( $middlewareResult = $this->result() ) === true )
            return true;

        if ( $middlewareResult === false ) {
            if ( str_starts_with( Router::$currentRoute->url, '/api/' ) )
                $middlewareResult = new JsonResponse(
                    [ 'error' => _t( 'access_denied' ) ], JsonResponse::HTTP_FORBIDDEN
                );

            else
                $middlewareResult = $this->redirectBack( errors: [ _t( 'Access Denied!' ) ] );

        }

        return $middlewareResult;
    }

    final public function dispatchEvent( AbstractEventListener $eventListener, mixed $args ): bool
    {
        if ( $eventListener::isExecuted() || !array_key_exists( static::class, $eventListener->getListeners() ) )
            return false;

        foreach ( $eventListener->getListeners() as $middleware => $listenerMethod ) {
            if ( $middleware === self::class )
                continue;

            $parameters = [];

            $listener = ( new ReflectionClass( $eventListener ) )
                ->getMethod( $listenerMethod );

            foreach ( $listener->getParameters() as $parameter )
                foreach ( $args as $argument ) {
                    $rc = ( new ReflectionClass( $argument ) )
                        ->getParentClass();

                    if ( $argument instanceof ( $parameter->getType()?->getName() ) ||
                        ( $rc instanceof ReflectionClass && $rc->getName() === ( $parameter->getType()?->getName() ) )
                    )
                        $parameters[ $parameter->getName() ] = $argument;
                }


            try {
                $listener->invoke( $eventListener, ...$parameters );
            } catch ( ArgumentCountError $e ) {
                foreach ( $args as $argument )
                    $availableArguments[] = $argument::class;

                throw new InvalidConfigurationException(
                    sprintf(
                        'The listener method "%s" of the "%s" class requires more arguments than the middleware "%s" can provide. Available arguments for "%s" middleware: %s',
                        $listenerMethod,
                        get_class( $eventListener ),
                        $middleware,
                        static::class,
                        implode( ', ', array_values( $availableArguments ) ),
                    ),
                    $e->getCode(), $e
                );
            }
        }

        $eventListener::markExecuted();

        return true;
    }


    final protected function changeHeaderTemplateClassName( string $headerClassName ): void
    {
        $this->kernel->setHeaderTemplateClassName( $headerClassName );
    }

    final protected function changeFooterTemplateClassName( string $footerClassName ): void
    {
        $this->kernel->setFooterTemplateClassName( $footerClassName );
    }

}
