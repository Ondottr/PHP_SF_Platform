<?php declare(strict_types=1);

namespace PHP_SF\Tests\System\Router;

use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Attributes\RouteApi;
use PHP_SF\System\Classes\Exception\InvalidRouteReturnTypeException;
use PHP_SF\System\Core\ApiResponse;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Router;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\JsonResponse;

// ---------------------------------------------------------------------------
// Controller fixtures covering the #[RouteApi] resolution matrix.
// ---------------------------------------------------------------------------

#[RouteApi]
final class StubApiClassController
{
    #[Route(url: '/items', httpMethod: 'GET')]
    public function items(): JsonResponse {}

    #[RouteApi(false)]
    #[Route(url: '/items/form', httpMethod: 'GET')]
    public function form(): void {}
}

final class StubMethodMarkedController
{
    #[RouteApi]
    #[Route(url: '/marked', httpMethod: 'GET')]
    public function marked(): JsonResponse {}

    #[Route(url: '/api/unmarked', httpMethod: 'GET')]
    public function unmarked(): void {}
}

#[RouteApi(false)]
final class StubNonApiClassController
{
    #[Route(url: '/api/opted_out', httpMethod: 'GET')]
    public function optedOut(): void {}
}

final class StubLegacyController
{
    #[Route(url: '/api/legacy', httpMethod: 'GET')]
    public function legacy(): JsonResponse {}

    #[Route(url: '/page', httpMethod: 'GET')]
    public function page(): void {}
}

// ---------------------------------------------------------------------------
// Controller fixtures covering the API route return-type validation.
// Method bodies are never invoked during route parsing.
// ---------------------------------------------------------------------------

#[RouteApi]
final class StubApiValidReturnTypesController
{
    #[Route(url: '/ok/json', httpMethod: 'GET')]
    public function json(): JsonResponse {}

    #[Route(url: '/ok/page', httpMethod: 'GET')]
    public function page(): Response {}

    #[Route(url: '/ok/union', httpMethod: 'GET')]
    public function union(): Response|JsonResponse {}

    #[Route(url: '/ok/envelope', httpMethod: 'GET')]
    public function envelope(): ApiResponse {}
}

#[RouteApi]
final class StubApiRedirectReturnController
{
    #[Route(url: '/bad/redirect', httpMethod: 'GET')]
    public function bad(): RedirectResponse {}
}

#[RouteApi]
final class StubApiUnionRedirectReturnController
{
    #[Route(url: '/bad/union', httpMethod: 'GET')]
    public function bad(): Response|RedirectResponse {}
}

#[RouteApi]
final class StubApiScalarReturnController
{
    #[Route(url: '/bad/scalar', httpMethod: 'GET')]
    public function bad(): string {}
}

final class StubLegacyRedirectReturnController
{
    #[Route(url: '/api/legacy_redirect', httpMethod: 'GET')]
    public function legacyRedirect(): RedirectResponse {}
}

final class StubPageRedirectReturnController
{
    #[Route(url: '/page/redirect', httpMethod: 'GET')]
    public function pageRedirect(): RedirectResponse {}
}

// ---------------------------------------------------------------------------
// Expose protected routesFromController() and the private routes list.
// ---------------------------------------------------------------------------

final class ApiTestableRouter extends Router
{
    public static function callRoutesFromController(string $namespace, string $fileName): void
    {
        static::routesFromController($namespace, $fileName);
    }

    public static function resetRoutesList(): void
    {
        (new ReflectionProperty(Router::class, 'routesList'))->setValue(null, []);
    }
}

final class RouterApiRouteTest extends TestCase
{
    private mixed $savedCurrentRoute;


    public function testClassLevelAttributeMarksAllRoutes(): void
    {
        $this->parseController(StubApiClassController::class);

        $routes = Router::getRoutesList();

        $this->assertTrue($routes['items']['api']);
        // method-level #[RouteApi(false)] overrides the class attribute
        $this->assertFalse($routes['form']['api']);
    }

    public function testMethodAttributeEntersExplicitModeForWholeClass(): void
    {
        $this->parseController(StubMethodMarkedController::class);

        $routes = Router::getRoutesList();

        $this->assertTrue($routes['marked']['api']);
        // the attribute is present in the class, so the deprecated /api/ prefix
        // heuristic is off — an unmarked route is non-API despite its URL
        $this->assertFalse($routes['unmarked']['api']);
    }

    public function testClassLevelFalseMarksAllRoutesNonApi(): void
    {
        $this->parseController(StubNonApiClassController::class);

        $routes = Router::getRoutesList();

        $this->assertFalse($routes['optedOut']['api']);
    }

    public function testNoAttributeKeepsLegacyNullFlag(): void
    {
        $this->parseController(StubLegacyController::class);

        $routes = Router::getRoutesList();

        // null = not declared, the deprecated prefix detection still decides
        $this->assertNull($routes['legacy']['api']);
        $this->assertNull($routes['page']['api']);
    }

    // -----------------------------------------------------------------------
    // checkApiRouteReturnType — API route return-type validation at parse time
    // -----------------------------------------------------------------------

    public function testApiRouteAllowsResponseFamilyReturnTypes(): void
    {
        $this->parseController(StubApiValidReturnTypesController::class);

        // JsonResponse, Response, their union and the ApiResponse subclass all pass
        $this->assertCount(4, Router::getRoutesList());
    }

    public function testApiRouteRejectsRedirectResponseReturnType(): void
    {
        $this->expectException(InvalidRouteReturnTypeException::class);
        $this->expectExceptionMessageMatches('/must never return a/');

        $this->parseController(StubApiRedirectReturnController::class);
    }

    public function testApiRouteRejectsUnionContainingRedirectResponse(): void
    {
        $this->expectException(InvalidRouteReturnTypeException::class);

        $this->parseController(StubApiUnionRedirectReturnController::class);
    }

    public function testApiRouteRejectsBuiltinReturnType(): void
    {
        $this->expectException(InvalidRouteReturnTypeException::class);

        $this->parseController(StubApiScalarReturnController::class);
    }

    public function testLegacyPrefixApiRouteRejectsRedirectResponseReturnType(): void
    {
        $this->expectException(InvalidRouteReturnTypeException::class);

        $this->parseController(StubLegacyRedirectReturnController::class);
    }

    public function testPageRouteAllowsRedirectResponseReturnType(): void
    {
        $this->parseController(StubPageRedirectReturnController::class);

        $this->assertArrayHasKey('pageRedirect', Router::getRoutesList());
    }

    public function testIsApiRouteUsesExplicitFlag(): void
    {
        Router::$currentRoute = (object) ['url' => '/anything', 'api' => true];
        $this->assertTrue(Router::isApiRoute());

        // explicit false wins over the /api/ URL
        Router::$currentRoute = (object) ['url' => '/api/anything', 'api' => false];
        $this->assertFalse(Router::isApiRoute());
    }

    public function testIsApiRouteAcceptsExplicitRouteArgument(): void
    {
        $this->assertTrue(Router::isApiRoute((object) ['url' => '/anything', 'api' => true]));
        $this->assertFalse(Router::isApiRoute((object) ['url' => '/api/anything', 'api' => false]));
    }

    public function testIsApiRouteFallsBackToPrefixWithDeprecation(): void
    {
        Router::$currentRoute = (object) ['url' => '/api/legacy'];

        [$result, $deprecations] = $this->captureDeprecations(static fn (): bool => Router::isApiRoute());

        $this->assertTrue($result);
        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('/api/legacy', $deprecations[0]);
        $this->assertStringContainsString('#[RouteApi]', $deprecations[0]);
    }

    public function testIsApiRoutePrefixMissTriggersNoDeprecation(): void
    {
        Router::$currentRoute = (object) ['url' => '/page'];

        [$result, $deprecations] = $this->captureDeprecations(static fn (): bool => Router::isApiRoute());

        $this->assertFalse($result);
        $this->assertCount(0, $deprecations);
    }

    public function testIsApiRouteWithoutCurrentRouteReturnsFalse(): void
    {
        Router::$currentRoute = null;

        $this->assertFalse(Router::isApiRoute());
    }

    protected function setUp(): void
    {
        $this->savedCurrentRoute = Router::$currentRoute;
        ApiTestableRouter::resetRoutesList();
    }

    protected function tearDown(): void
    {
        Router::$currentRoute = $this->savedCurrentRoute;
        ApiTestableRouter::resetRoutesList();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @param class-string $controllerClass
     */
    private function parseController(string $controllerClass): void
    {
        $namespace = substr($controllerClass, 0, (int) strrpos($controllerClass, '\\'));
        $fileName = substr($controllerClass, (int) strrpos($controllerClass, '\\') + 1);

        ApiTestableRouter::callRoutesFromController($namespace, $fileName);
    }

    /**
     * Runs the callback with an E_USER_DEPRECATED capturing handler installed.
     *
     * @param callable(): bool $callback
     *
     * @return array{bool, list<string>} callback result and captured deprecation messages
     */
    private function captureDeprecations(callable $callback): array
    {
        $deprecations = [];

        set_error_handler(
            static function (int $errno, string $errstr) use (&$deprecations): bool {
                $deprecations[] = $errstr;

                return true;
            },
            E_USER_DEPRECATED,
        );

        try {
            $result = $callback();
        } finally {
            restore_error_handler();
        }

        return [$result, $deprecations];
    }
}
