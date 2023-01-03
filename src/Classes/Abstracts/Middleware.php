<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Interface\EventSubscriberInterface;
use PHP_SF\System\Interface\MiddlewareInterface;
use PHP_SF\System\Router;
use PHP_SF\System\Traits\RedirectTrait;
use ReflectionClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use function array_key_exists;

abstract class Middleware implements MiddlewareInterface, EventSubscriberInterface
{
    use RedirectTrait;


    public function __construct(
        protected Request|null $request
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


    final public function dispatchEvent( AbstractEventListener $eventListener, mixed $args ): bool
    {
        if ( $eventListener::isExecuted() || !array_key_exists( static::class, $eventListener->getListeners() ) )
            return false;

        foreach ( $eventListener->getListeners() as $middleware => $listenerMethod ) {
            if ( $middleware === static::class )
                continue;

            $parameters = [];

            $listener = ( new ReflectionClass( $eventListener ) )
                ->getMethod( $listenerMethod );

            foreach ( $listener->getParameters() as $parameter )
                foreach ( $args as $argument )
                    if ( $argument instanceof ( $parameter->getType()?->getName() ) )
                        $parameters[ $parameter->getName() ] = $argument;


            $listener->invoke( $eventListener, ...$parameters );
        }

        $eventListener::markExecuted();

        return true;
    }

}
