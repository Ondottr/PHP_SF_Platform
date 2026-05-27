<?php declare(strict_types=1);

namespace PHP_SF\Tests\System\Classes\MiddlewareChecks;

use PHP_SF\System\Classes\Abstracts\Middleware;
use PHP_SF\System\Classes\Exception\RouteMiddlewareException;
use PHP_SF\System\Classes\MiddlewareChecks\MiddlewareAll;
use PHP_SF\System\Classes\MiddlewareChecks\MiddlewareAny;
use PHP_SF\System\Classes\MiddlewareChecks\MiddlewareCustom;
use PHP_SF\System\Classes\MiddlewareChecks\MiddlewaresExecutor;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Router;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class MiddlewareTrue extends Middleware
{
    public function result(): bool { return true; }
}

final class MiddlewareFalse extends Middleware
{
    public function result(): bool { return false; }
}

final class MiddlewareJsonResponse extends Middleware
{
    public function result(): JsonResponse { return new JsonResponse( [ 'test' => 'test' ] ); }
}

final class MiddlewareRedirectResponse extends Middleware
{
    public function result(): RedirectResponse { return $this->redirectTo( 'welcome_page' ); }
}

final class MiddlewaresExecutorTest extends TestCase
{

    /** @noinspection GlobalVariableUsageInspection */
    protected function setUp(): void
    {
        $ref = new ReflectionProperty( Router::class, 'requestData' );
        $ref->setAccessible( true );
        $ref->setValue( null, new Request() );
    }

    public function testEmpty1(): void
    {
        $me = new MiddlewaresExecutor( [] );
        $this->assertTrue($me->execute());

        $me = new MiddlewaresExecutor( '' );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage('Middleware must be a non-empty string');
        $me->execute();
    }

    public function testEmpty2(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareAll::class . ' array must not be empty!');
        $me->execute();
    }

    public function testEmpty3(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => [] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareAny::class . ' array must not be empty!');
        $me->execute();
    }

    public function testEmpty4(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareCustom::class . ' array must not be empty!');
        $me->execute();
    }

    public function testEmpty5(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareAny::class => [], MiddlewareAll::class => [] ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareAll::class . ' array must not be empty!');
        $me->execute();
    }

    public function testEmpty6(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareAny::class => [ MiddlewareTrue::class ], MiddlewareAll::class => [] ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareAll::class . ' array must not be empty!');
        $me->execute();
    }

    public function testAllRedirectResponse(): void
    {
        Router::$currentRoute = (object) [ 'url' => '/middleware_testing' ];

        $me = new MiddlewaresExecutor( MiddlewareTrue::class );
        $this->assertTrue($me->execute());

        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ MiddlewareTrue::class ] ] );
        $this->assertTrue($me->execute());

        $me = new MiddlewaresExecutor( MiddlewareFalse::class );
        $this->assertInstanceOf( RedirectResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ MiddlewareFalse::class ] ] );
        $this->assertInstanceOf( RedirectResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ MiddlewareTrue::class, MiddlewareFalse::class ] ] );
        $this->assertInstanceOf( RedirectResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ MiddlewareFalse::class, MiddlewareTrue::class ] ] );
        $this->assertInstanceOf( RedirectResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ MiddlewareRedirectResponse::class ] ] );
        $this->assertInstanceOf( RedirectResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ [ /** ... */ ] ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareAll::class . ' array must contain only strings!');
        $me->execute();
    }

    public function testAllJsonResponse(): void
    {
        Router::$currentRoute = (object) [ 'url' => '/api/middleware_testing' ];

        $me = new MiddlewaresExecutor( MiddlewareFalse::class );
        $this->assertInstanceOf( JsonResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ MiddlewareJsonResponse::class ] ] );
        $this->assertInstanceOf( JsonResponse::class, $me->execute() );
    }

    public function testAnyRedirectResponse(): void
    {
        Router::$currentRoute = (object) [ 'url' => '/middleware_testing' ];

        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => [ MiddlewareTrue::class ] ] );
        $this->assertTrue($me->execute());

        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => [ MiddlewareFalse::class ] ] );
        $this->assertInstanceOf( RedirectResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => [ MiddlewareTrue::class, MiddlewareFalse::class ] ] );
        $this->assertTrue($me->execute());

        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => [ MiddlewareFalse::class, MiddlewareTrue::class ] ] );
        $this->assertTrue($me->execute());

        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => [ MiddlewareRedirectResponse::class ] ] );
        $this->assertInstanceOf( RedirectResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => [ [ /** ... */ ] ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareAny::class . ' array must contain only strings!');
        $me->execute();
    }

    public function testAnyJsonResponse(): void
    {
        Router::$currentRoute = (object) [ 'url' => '/api/middleware_testing' ];

        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => [ MiddlewareJsonResponse::class ] ] );
        $this->assertInstanceOf( JsonResponse::class, $me->execute() );
    }

    public function testCustom1(): void
    {
        Router::$currentRoute = (object) [ 'url' => '/middleware_testing' ];

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareAll::class => [ MiddlewareTrue::class ] ] ] );
        $this->assertTrue($me->execute());

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareAll::class => [ MiddlewareFalse::class, MiddlewareTrue::class ] ] ] );
        $this->assertInstanceOf( RedirectResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareAll::class => [ MiddlewareTrue::class ] ] ] );
        $this->assertTrue($me->execute());

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareAll::class => [ MiddlewareTrue::class ], MiddlewareAny::class => [ MiddlewareFalse::class, MiddlewareTrue::class ] ] ] );
        $this->assertTrue($me->execute());
    }

    public function testCustom2(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => [ /** ... */ ], MiddlewareAll::class => [ /** ... */ ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage('First level of an array must contain only one key!');
        $me->execute();
    }

    public function testCustom3(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ /** ... */ ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareCustom::class . ' array must not be empty!');
        $me->execute();
    }

    public function testCustom4(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ /** ... */ ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareAll::class . ' array must not be empty!');
        $me->execute();
    }

    public function testCustom5(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => [ /** ... */ ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareAny::class . ' array must not be empty!');
        $me->execute();
    }

    public function testCustom6(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => MiddlewareFalse::class ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareAny::class . ' array must contain only arrays with strings!');
        $me->execute();
    }

    public function testCustom7(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ [ /** ... */ ], [ /** ... */ ], [ /** ... */ ] ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareCustom::class . ' array must contain only arrays with max 2 elements!');
        $me->execute();
    }

    public function testCustom8(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ [ /** ... */ ], '[ /** ... */ ]' ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareCustom::class . ' array must contain only arrays with max 2 elements and all elements must be arrays!');
        $me->execute();
    }

    public function testKeys1(): void
    {
        Router::$currentRoute = (object) [ 'url' => '/middleware_testing' ];

        $me = new MiddlewaresExecutor( [ 'invalidKey' => [ MiddlewareTrue::class, MiddlewareFalse::class ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage('Middleware array keys must be a valid class which extends MiddlewareCheck!');
        $me->execute();
    }

    public function testKeys2(): void
    {
        Router::$currentRoute = (object) [ 'url' => '/middleware_testing' ];

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ 'invalidKey' => [ MiddlewareFalse::class, MiddlewareTrue::class ] ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareCustom::class . ' array must contain only arrays with max 2 elements and all keys must be ' . MiddlewareAll::class . ' or ' . MiddlewareAny::class);
        $me->execute();
    }

    public function testAllUnique1(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareTrue::class, MiddlewareTrue::class ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareAll::class . ' array must contain unique values only!');
        $me->execute();
    }

    public function testAllUnique2(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ MiddlewareTrue::class, MiddlewareTrue::class ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareAll::class . ' array must contain unique values only!');
        $me->execute();
    }

    public function testAllUnique3(): void
    {
        Router::$currentRoute = (object) [ 'url' => '/middleware_testing' ];

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareAny::class => [ MiddlewareTrue::class, MiddlewareTrue::class ] ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareAny::class . ' array must contain unique values only!');
        $me->execute();
    }

    public function testAllUnique4(): void
    {
        Router::$currentRoute = (object) [ 'url' => '/middleware_testing' ];

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareAny::class => [ MiddlewareTrue::class, MiddlewareFalse::class ], MiddlewareAll::class => [ MiddlewareTrue::class, MiddlewareTrue::class ] ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareAll::class . ' array must contain unique values only!');
        $me->execute();
    }

    public function testAllUnique5(): void
    {
        Router::$currentRoute = (object) [ 'url' => '/middleware_testing' ];

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareAny::class => [ MiddlewareFalse::class, MiddlewareFalse::class ] ] ] );
        $this->expectException(RouteMiddlewareException::class);
        $this->expectExceptionMessage(MiddlewareAny::class . ' array must contain unique values only!');
        $me->execute();
    }

}
