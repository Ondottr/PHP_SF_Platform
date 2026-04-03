<?php declare( strict_types=1 );

namespace PHP_SF\Framework\EventListener;

use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Core\Response as PhpSfResponse;
use PHP_SF\System\Router;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Bridges PHP_SF framework controllers into Symfony's HttpKernel for functional tests.
 *
 * In production, Router::init() handles PHP_SF controller dispatch entirely (and exits).
 * In tests, Symfony's KernelBrowser drives the request cycle, so we need to:
 *   1. Properly instantiate PHP_SF controllers with the current Symfony Request.
 *   2. Capture their rendered output into the Response's content property so
 *      KernelBrowser can read it via getContent().
 *
 * Registered only in the test environment via config/services.yaml "when@test".
 */
final class PhpSfControllerListener implements EventSubscriberInterface
{

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [ 'onKernelController', 10 ],
            KernelEvents::RESPONSE   => [ 'onKernelResponse', 10 ],
        ];
    }

    public function onKernelController( ControllerEvent $event ): void
    {
        $controller = $event->getController();

        // PhpSfRouteLoader registers controllers as 'ClassName::method' strings.
        if ( is_string( $controller ) && str_contains( $controller, '::' ) )
            $controller = explode( '::', $controller, 2 );

        if ( !is_array( $controller ) || count( $controller ) !== 2 )
            return;

        [ $classOrInstance, $method ] = $controller;
        $class = is_object( $classOrInstance ) ? $classOrInstance::class : $classOrInstance;

        if ( !is_a( $class, AbstractController::class, true ) )
            return;

        $request  = $event->getRequest();
        $routeUrl = $request->attributes->get( '_php_sf_url', '' );

        // Set Router::$currentRoute so Response::send() / captureContent() can
        // check the URL (e.g. to skip header/footer for /api/ routes).
        Router::$currentRoute = (object) [ 'url' => $routeUrl ];

        // Replace the callable: ControllerResolver would do `new $class()` (no request),
        // here we inject the Symfony Request and forward route params correctly.
        $event->setController( static function () use ( $class, $method, $request ): mixed {
            $instance = new $class( $request );

            $params = [];
            foreach ( $request->attributes->all() as $key => $value ) {
                if ( str_starts_with( $key, '_' ) )
                    continue;
                $params[ $key ] = $value;
            }

            return $instance->$method( ...$params );
        } );
    }

    public function onKernelResponse( ResponseEvent $event ): void
    {
        $response = $event->getResponse();

        if ( !( $response instanceof PhpSfResponse ) )
            return;

        $routeUrl = $event->getRequest()->attributes->get( '_php_sf_url', '/' );
        $response->captureContent( $routeUrl );
    }

}
