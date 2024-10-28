<?php declare( strict_types=1 );
/**
 * Created by PhpStorm.
 * User: ondottr
 * Date: 15/02/2023
 * Time: 10:19 am
 */

namespace PHP_SF\Tests\System\Classes\MiddlewareChecks;

use PHP_SF\Framework\Http\Middleware\api;
use PHP_SF\Framework\Http\Middleware\auth;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Classes\Abstracts\Middleware;
use PHP_SF\System\Classes\Exception\RouteMiddlewareException;
use PHP_SF\System\Classes\MiddlewareChecks\MiddlewareAll;
use PHP_SF\System\Classes\MiddlewareChecks\MiddlewareAny;
use PHP_SF\System\Classes\MiddlewareChecks\MiddlewareCustom;
use PHP_SF\System\Classes\MiddlewareChecks\MiddlewaresExecutor;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Kernel;
use PHP_SF\System\Router;
use PHP_SF\Templates\base;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class MiddlewareTrue extends Middleware
{ public function result(): bool { return true; } }

final class MiddlewareFalse extends Middleware
{ public function result(): bool { return false; } }

final class MiddlewareJsonResponse extends Middleware
{ public function result(): JsonResponse { return new JsonResponse( [ 'test' => 'test' ] ); } }

final class MiddlewareRedirectResponse extends Middleware
{ public function result(): RedirectResponse { return $this->redirectTo( 'welcome_page' ); } }


final class MiddlewaresExecutorTest extends TestCase
{

    private Kernel $kernel;
    private Request $request;
    private AbstractController $controller;


    private function params(): array
    {
        return [ $this->request, $this->kernel, $this->controller ];
    }


    /**
     * @noinspection GlobalVariableUsageInspection
     */
    public function __construct(string $methodName)
    {
        $this->kernel = $GLOBALS['kernel'];

        $this->request = new Request;

        $this->controller = new class( $this->request ) extends AbstractController {
            /** @noinspection PhpParamsInspection */
            #[Route( middleware: [ [ MiddlewareAll::class => [ auth::class, api::class ] ] ])]
            public function custom_test_page(): Response|RedirectResponse|JsonResponse
            {
                return $this->render( base::class, ['title' => 'Test'] );
                // or
                // return new JsonResponse( ['test' => 'test'] );
                // or
                // return new RedirectResponse( 'welcome_page' );
            }
        };

        parent::__construct($methodName);
    }


    public function testEmpty1(): void
    {
        $me = new MiddlewaresExecutor( [], $this->request, $this->kernel, $this->controller );
        $this->assertTrue( $me->execute() );

        $me = new MiddlewaresExecutor( '', $this->request, $this->kernel, $this->controller );
        $this->expectException( RouteMiddlewareException::class);
        $this->expectExceptionMessage('Middleware must be a non-empty string');
        $me->execute();
    }

    public function testEmpty2(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [] ] , $this->request, $this->kernel, $this->controller );
        $this->expectException( RouteMiddlewareException::class);
        $this->expectExceptionMessage( MiddlewareAll::class . ' array must not be empty!' );
        $me->execute();
    }

    public function testEmpty3(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => [] ] , $this->request, $this->kernel, $this->controller );
        $this->expectException( RouteMiddlewareException::class);
        $this->expectExceptionMessage( MiddlewareAny::class . ' array must not be empty!' );
        $me->execute();
    }

    public function testEmpty4(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [] ] , $this->request, $this->kernel, $this->controller );
        $this->expectException( RouteMiddlewareException::class);
        $this->expectExceptionMessage( MiddlewareCustom::class . ' array must not be empty!' );
        $me->execute();
    }

    public function testEmpty5(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareANY::class => [], MiddlewareALL::class => [], ] ] , $this->request, $this->kernel, $this->controller );
        $this->expectException( RouteMiddlewareException::class);
        $this->expectExceptionMessage( MiddlewareALL::class . ' array must not be empty!' );
        $me->execute();
    }

    public function testEmpty6(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareANY::class => [ MiddlewareTrue::class ], MiddlewareALL::class => [], ] ] , $this->request, $this->kernel, $this->controller );
        $this->expectException( RouteMiddlewareException::class);
        $this->expectExceptionMessage( MiddlewareALL::class . ' array must not be empty!' );
        $me->execute();
    }


    public function testAllRedirectResponse(): void
    {
        Router::$currentRoute = (object)[
            'url' => '/middleware_testing',
        ];

        $me = new MiddlewaresExecutor( MiddlewareTrue::class, ...$this->params() );
        $this->assertTrue( $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ MiddlewareTrue::class ] ], ...$this->params() );
        $this->assertTrue( $me->execute() );

        $me = new MiddlewaresExecutor( MiddlewareFalse::class, ...$this->params() );
        $this->assertInstanceof( RedirectResponse::class, $me->execute() );


        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ MiddlewareFalse::class ] ], ...$this->params() );
        $this->assertInstanceof( RedirectResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ MiddlewareTrue::class, MiddlewareFalse::class ] ], ...$this->params() );
        $this->assertInstanceof( RedirectResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ MiddlewareFalse::class, MiddlewareTrue::class ] ], ...$this->params() );
        $this->assertInstanceof( RedirectResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ MiddlewareRedirectResponse::class ] ], ...$this->params() );
        $this->assertInstanceof( RedirectResponse::class, $me->execute() );


        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ [ /** ... */ ] ] ], ...$this->params() );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( MiddlewareAll::class . ' array must contain only strings!' );
        $me->execute();
    }

    public function testAllJsonResponse(): void
    {
        Router::$currentRoute = (object)[
            'url' => '/api/middleware_testing',
        ];

        $me = new MiddlewaresExecutor( MiddlewareFalse::class, ...$this->params() );
        $this->assertInstanceof( JsonResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ MiddlewareJsonResponse::class ] ], ...$this->params() );
        $this->assertInstanceof( JsonResponse::class, $me->execute() );
    }

    public function testAnyRedirectResponse(): void
    {
        Router::$currentRoute = (object)[
            'url' => '/middleware_testing',
        ];

        $me = new MiddlewaresExecutor( [ MiddlewareANY::class => [ MiddlewareTrue::class ] ], ...$this->params() );
        $this->assertTrue( $me->execute() );


        $me = new MiddlewaresExecutor( [ MiddlewareANY::class => [ MiddlewareFalse::class ] ], ...$this->params() );
        $this->assertInstanceof( RedirectResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareANY::class => [ MiddlewareTrue::class, MiddlewareFalse::class ] ], ...$this->params() );
        $this->assertTrue( $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareANY::class => [ MiddlewareFalse::class, MiddlewareTrue::class ] ], ...$this->params() );
        $this->assertTrue( $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareANY::class => [ MiddlewareRedirectResponse::class ] ], ...$this->params() );
        $this->assertInstanceof( RedirectResponse::class, $me->execute() );


        $me = new MiddlewaresExecutor( [ MiddlewareANY::class => [ [ /** ... */ ] ] ], ...$this->params() );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( MiddlewareANY::class . ' array must contain only strings!' );
        $me->execute();
    }

    public function testAnyJsonResponse(): void
    {
        Router::$currentRoute = (object)[
            'url' => '/api/middleware_testing',
        ];

        $me = new MiddlewaresExecutor( [ MiddlewareANY::class => [ MiddlewareJsonResponse::class ] ], ...$this->params() );
        $this->assertInstanceof( JsonResponse::class, $me->execute() );
    }


    public function testCustom1(): void
    {
        Router::$currentRoute = (object)[
            'url' => '/middleware_testing',
        ];

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareAll::class => [ MiddlewareTrue::class ] ] ], ...$this->params() );
        $this->assertTrue( $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareAll::class => [ MiddlewareFalse::class, MiddlewareTrue::class ] ] ], ...$this->params() );
        $this->assertInstanceof( RedirectResponse::class, $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareAll::class => [ MiddlewareTrue::class ] ] ], ...$this->params() );
        $this->assertTrue( $me->execute() );

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareAll::class => [ MiddlewareTrue::class ], MiddlewareAny::class => [ MiddlewareFalse::class, MiddlewareTrue::class ] ] ], ...$this->params() );
        $this->assertTrue( $me->execute() );
    }

    public function testCustom2(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => [ /** ... */ ], MiddlewareAll::class => [ /** ... */ ] ], ...$this->params() );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( 'First level of an array must contain only one key!' );
        $me->execute();
    }

    public function testCustom3(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ /** ... */ ] ], ...$this->params() );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( MiddlewareCustom::class . ' array must not be empty!' );
        $me->execute();
    }

    public function testCustom4(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ /** ... */ ] ], ...$this->params() );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( MiddlewareAll::class . ' array must not be empty!' );
        $me->execute();
    }

    public function testCustom5(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => [ /** ... */ ] ], ...$this->params() );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( MiddlewareAny::class . ' array must not be empty!' );
        $me->execute();
    }

    public function testCustom6(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareAny::class => MiddlewareFalse::class ], ...$this->params() );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( MiddlewareAny::class . ' array must contain only arrays with strings!' );
        $me->execute();
    }

    public function testCustom7(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ [ /** ... */ ], [ /** ... */ ], [ /** ... */ ], ] ], ...$this->params() );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( MiddlewareCustom::class . ' array must contain only arrays with max 2 elements!' );
        $me->execute();
    }

    public function testCustom8(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ [ /** ... */ ], '[ /** ... */ ]', ] ], ...$this->params() );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( MiddlewareCustom::class . ' array must contain only arrays with max 2 elements and all elements must be arrays!' );
        $me->execute();
    }


    public function testKeys1(): void
    {
        Router::$currentRoute = (object)[
            'url' => '/middleware_testing',
        ];

        $me = new MiddlewaresExecutor( [ 'invalidKey' => [ MiddlewareTrue::class, MiddlewareFalse::class ] ], ...$this->params() );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( 'Middleware array keys must be a valid class which extends MiddlewareCheck!' );
        $me->execute();
    }

    public function testKeys2(): void
    {
        Router::$currentRoute = (object)[
            'url' => '/middleware_testing',
        ];

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ 'invalidKey' => [ MiddlewareFalse::class, MiddlewareTrue::class ] ] ], ...$this->params() );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( MiddlewareCustom::class . ' array must contain only arrays with max 2 elements and all keys must be ' . MiddlewareAll::class .' or ' . MiddlewareAny::class );
        $me->execute();
    }


    public function testAllUnique1(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareTrue::class, MiddlewareTrue::class ], ...$this->params() );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( MiddlewareAll::class . ' array must contain unique values only!' );
        $me->execute();
    }

    public function testAllUnique2(): void
    {
        $me = new MiddlewaresExecutor( [ MiddlewareAll::class => [ MiddlewareTrue::class, MiddlewareTrue::class ] ], ...$this->params() );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( MiddlewareAll::class . ' array must contain unique values only!' );
        $me->execute();
    }

    public function testAllUnique3(): void
    {
        Router::$currentRoute = (object)[
            'url' => '/middleware_testing',
        ];

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareANY::class => [ MiddlewareTrue::class, MiddlewareTrue::class ] ] ] , $this->request, $this->kernel, $this->controller );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( MiddlewareANY::class . ' array must contain unique values only!' );
        $me->execute();
    }

    public function testAllUnique4(): void
    {
        Router::$currentRoute = (object)[
            'url' => '/middleware_testing',
        ];

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareANY::class => [ MiddlewareTrue::class, MiddlewareFalse::class, ], MiddlewareALL::class => [ MiddlewareTrue::class, MiddlewareTrue::class, ], ] ] , $this->request, $this->kernel, $this->controller );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( MiddlewareALL::class . ' array must contain unique values only!' );
        $me->execute();
    }

    public function testAllUnique5(): void
    {
        Router::$currentRoute = (object)[
            'url' => '/middleware_testing',
        ];

        $me = new MiddlewaresExecutor( [ MiddlewareCustom::class => [ MiddlewareANY::class => [ MiddlewareFalse::class, MiddlewareFalse::class ] ] ] , $this->request, $this->kernel, $this->controller );
        $this->expectException( RouteMiddlewareException::class );
        $this->expectExceptionMessage( MiddlewareANY::class . ' array must contain unique values only!' );
        $me->execute();
    }


}
