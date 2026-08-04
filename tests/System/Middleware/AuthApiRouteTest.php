<?php declare(strict_types=1);

namespace PHP_SF\Tests\System\Middleware;

use PHP_SF\Framework\Http\Middleware\auth;
use PHP_SF\System\Core\ApiResponse;
use PHP_SF\System\Router;
use PHPUnit\Framework\TestCase;

/**
 * The auth middleware must answer unauthenticated requests to API routes with the
 * JSON 401 envelope — decided via Router::isApiRoute(), i.e. the #[RouteApi] flag
 * first and the deprecated /api/ prefix as fallback — never with a redirect.
 *
 * Guards the prefix-check-to-isApiRoute() switch: if the runtime api-flag wiring
 * regresses, an auth-protected #[RouteApi] route whose URL is not under /api/
 * would fall back to the prefix and redirect to the login page instead of
 * returning 401 JSON.
 */
final class AuthApiRouteTest extends TestCase
{
    private mixed $savedCurrentRoute;


    public function testUnauthenticatedApiFlaggedRouteReturnsJson401(): void
    {
        Router::$currentRoute = (object) ['url' => '/anything', 'api' => true];

        $result = (new auth())->result();

        $this->assertInstanceOf(ApiResponse::class, $result);
        $this->assertSame(401, $result->getStatusCode());
    }

    public function testApiFlagOutweighsNonApiUrl(): void
    {
        // URL deliberately not under /api/: only the #[RouteApi] flag can route this to 401
        Router::$currentRoute = (object) ['url' => '/example/api/protected', 'api' => true];

        $result = (new auth())->result();

        $this->assertInstanceOf(ApiResponse::class, $result);
        $this->assertSame(401, $result->getStatusCode());
    }

    public function testLegacyPrefixFallbackStillReturnsJson401(): void
    {
        Router::$currentRoute = (object) ['url' => '/api/legacy', 'api' => null];

        $result = (new auth())->result();

        $this->assertInstanceOf(ApiResponse::class, $result);
        $this->assertSame(401, $result->getStatusCode());
    }

    protected function setUp(): void
    {
        $this->savedCurrentRoute = Router::$currentRoute;
        auth::$user = false;
    }

    protected function tearDown(): void
    {
        Router::$currentRoute = $this->savedCurrentRoute;
    }
}
