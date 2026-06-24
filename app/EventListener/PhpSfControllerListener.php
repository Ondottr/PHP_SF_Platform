<?php declare(strict_types=1);

namespace PHP_SF\Framework\EventListener;

use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHP_SF\System\Core\RedirectResponse as PhpSfRedirectResponse;
use PHP_SF\System\Core\Response as PhpSfResponse;
use PHP_SF\System\Router;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
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
 *   3. Convert PHP_SF's in-process RedirectResponse into a proper HTTP 302 so that
 *      Codeception's followRedirect() works, and carry flash data to the next request.
 *
 * Flash data (errors/messages/form_data) is carried via a static in-process bag.
 * Codeception runs all requests in the same PHP process via KernelBrowser, so a
 * static property is the simplest reliable mechanism — no session complexity needed.
 *
 * Registered only in the test environment via config/services.yaml "when@test".
 */
final class PhpSfControllerListener implements EventSubscriberInterface
{
    /**
     * Flash bag: populated on redirect response, consumed on the next request.
     *
     * @var array<string, mixed>
     */
    private static array $flashBag = [];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
            KernelEvents::CONTROLLER => ['onKernelController', 10],
            KernelEvents::RESPONSE => ['onKernelResponse', 10],
        ];
    }

    /**
     * On every main request: consume the static flash bag and populate the globals
     * that PHP_SF templates read via getErrors(), getMessages(), formValue().
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Router::$requestData is normally set by Router::init() which does not run during
        // functional tests. Seed it here so r() works inside PHP_SF controller methods.
        $ref = new \ReflectionProperty(Router::class, 'requestData');
        $ref->setValue(null, $event->getRequest());

        $GLOBALS['errors'] = self::$flashBag['errors'] ?? [];
        $GLOBALS['messages'] = self::$flashBag['messages'] ?? [];
        $GLOBALS['form_data'] = self::$flashBag['formData'] ?? [];
        self::$flashBag = [];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        // PhpSfRouteLoader registers controllers as 'ClassName::method' strings.
        if (is_string($controller) && str_contains($controller, '::')) {
            $controller = explode('::', $controller, 2);
        }

        if (!is_array($controller) || 2 !== count($controller)) {
            return;
        }

        [$classOrInstance, $method] = $controller;
        $class = is_object($classOrInstance) ? $classOrInstance::class : $classOrInstance;

        if (!is_a($class, AbstractController::class, true)) {
            return;
        }

        $request = $event->getRequest();
        $routeUrl = $request->attributes->get('_php_sf_url', '');

        // Reconstruct the currentRoute object that templates and Response::send() rely on.
        // Normally set by Router::setCurrentRoute(); in tests we rebuild it from request attributes.
        [$ctrlClass, $ctrlMethod] = explode('::', $request->attributes->get('_controller', '::'), 2);
        Router::$currentRoute = (object) [
            'url' => $routeUrl,
            'name' => $request->attributes->get('_route', ''),
            'class' => $ctrlClass,
            'method' => $ctrlMethod,
            'httpMethod' => $request->getMethod(),
            'middleware' => $request->attributes->get('_php_sf_middleware', []),
        ];

        // Replace the callable: ControllerResolver would do `new $class()`,
        // here we forward route params correctly.
        $event->setController(static function () use ($class, $method, $request, $routeUrl): mixed {
            $instance = new $class();

            $rawParams = [];
            foreach ($request->attributes->all() as $key => $value) {
                if (str_starts_with($key, '_')) {
                    continue;
                }
                $rawParams[$key] = $value;
            }

            if (empty($rawParams)) {
                return $instance->$method();
            }

            // Mirror Router::setRouteParameters(): positional matching by URL placeholder order.
            // Method param N maps to URL placeholder N — names may differ for entity params.
            $urlPlaceholderNames = array_keys($rawParams);
            $reflection = new \ReflectionMethod($class, $method);
            $params = [];

            foreach ($reflection->getParameters() as $index => $rp) {
                $urlPlaceholderName = $urlPlaceholderNames[$index] ?? null;
                if (null === $urlPlaceholderName) {
                    break;
                }

                $value = $rawParams[$urlPlaceholderName];
                $reflectionType = $rp->getType();
                $type = $reflectionType instanceof \ReflectionNamedType ? $reflectionType->getName() : null;

                if (null !== $type && is_a($type, AbstractEntity::class, true)) {
                    $entity = $type::findOneBy([$urlPlaceholderName => $value]);

                    if (null === $entity && false === $reflectionType->allowsNull()) {
                        if (str_starts_with($routeUrl, '/api/')) {
                            return new JsonResponse(['error' => _t('common.errors.not_found')], JsonResponse::HTTP_NOT_FOUND);
                        }

                        return new PhpSfResponse(status: PhpSfResponse::HTTP_NOT_FOUND);
                    }

                    $params[] = $entity;
                } else {
                    if (null !== $type) {
                        settype($value, $type);
                    }
                    $params[] = $value;
                }
            }

            return $instance->$method(...$params);
        });
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        // ── PHP_SF RedirectResponse ───────────────────────────────────────────
        // Production: RedirectResponse::send() re-dispatches via Router::init() (exit-based).
        // Tests: convert to proper HTTP 302, carry flash data via the static bag.
        if ($response instanceof PhpSfRedirectResponse) {
            $targetUrl = $response->getTargetUrl();
            $requestDataId = $response->getRequestDataId();

            if (null !== $requestDataId) {
                $urlKey = hash('xxh3', $targetUrl);
                $cacheKey = "$urlKey:$requestDataId";

                $rawErrors = ca()->get(":ERRORS:$cacheKey");
                $rawMessages = ca()->get(":MESSAGES:$cacheKey");
                $rawFormData = ca()->get(":FORM_DATA:$cacheKey");

                self::$flashBag = [
                    'errors' => null !== $rawErrors ? j_decode($rawErrors, true) : [],
                    'messages' => null !== $rawMessages ? j_decode($rawMessages, true) : [],
                    'formData' => null !== $rawFormData ? j_decode($rawFormData, true) : [],
                ];
            }

            $event->setResponse(new SymfonyRedirectResponse($targetUrl));

            return;
        }

        // ── PHP_SF Response ───────────────────────────────────────────────────
        // Render the page (header + view + footer) into $response->content so that
        // KernelBrowser can read it via getContent().
        if ($response instanceof PhpSfResponse) {
            $routeUrl = $event->getRequest()->attributes->get('_php_sf_url', '/');
            $response->captureContent($routeUrl);
        }
    }
}
