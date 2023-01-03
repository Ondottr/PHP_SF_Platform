<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use ArgumentCountError;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Interface\EventSubscriberInterface;
use PHP_SF\System\Interface\MiddlewareInterface;
use PHP_SF\System\Kernel;
use PHP_SF\System\Router;
use PHP_SF\System\Traits\RedirectTrait;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use function array_key_exists;

abstract class Middleware implements MiddlewareInterface, EventSubscriberInterface
{
    use RedirectTrait;


    public function __construct(
        protected readonly Request|null $request,
        private readonly Kernel $kernel,
    )
    {
        if (( $middlewareResult = $this->result() ) === true)
            return;

        if ($middlewareResult === false) {
            if (str_starts_with(Router::$currentRoute->url, '/api/')) {
                $middlewareResult = new JsonResponse([ 'error' => _t('access_denied') ], JsonResponse::HTTP_FORBIDDEN);
            } else {
                $middlewareResult = $this->redirectTo('access_denied_page');
            }
        }

        if ($middlewareResult instanceof Response ||
            $middlewareResult instanceof RedirectResponse ||
            $middlewareResult instanceof JsonResponse
        ) {
            $middlewareResult->send();
        }


        die();
    }

    abstract public function result(): bool|JsonResponse|RedirectResponse|Response;

    final protected function changeHeaderTemplateClassName( string $headerClassName ): void
    {
        $this->kernel->setHeaderTemplateClassName( $headerClassName );
    }

    final protected function changeFooterTemplateClassName( string $footerClassName ): void
    {
        $this->kernel->setFooterTemplateClassName( $footerClassName );
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

}
