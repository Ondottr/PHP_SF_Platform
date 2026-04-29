<?php declare( strict_types=1 );

namespace PHP_SF\Tests\System\Core;

use PHP_SF\System\Core\PhpSfContext;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;

final class PhpSfContextTest extends TestCase
{

    protected function setUp(): void    { $this->reset(); }
    protected function tearDown(): void { $this->reset(); }


    public function testCurrentReturnsNullInitially(): void
    {
        $this->assertNull( PhpSfContext::current() );
    }

    public function testSetAndCurrentRoundtrip(): void
    {
        $ctx = $this->ctx( '/test' );
        PhpSfContext::set( $ctx );

        $this->assertSame( $ctx, PhpSfContext::current() );
    }

    public function testSetOverwritesPrevious(): void
    {
        PhpSfContext::set( $this->ctx( '/first' ) );
        $second = $this->ctx( '/second' );
        PhpSfContext::set( $second );

        $this->assertSame( $second, PhpSfContext::current() );
    }

    public function testGetRoute(): void
    {
        $route = (object)[ 'url' => '/foo', 'name' => 'foo_route' ];
        PhpSfContext::set( new PhpSfContext( $route, [], new Request(), $GLOBALS['kernel'] ) );

        $this->assertSame( $route, PhpSfContext::current()->getRoute() );
    }

    public function testGetMiddleware(): void
    {
        $middleware = [ 'SomeMiddleware', 'AnotherMiddleware' ];
        PhpSfContext::set( new PhpSfContext( (object)[], $middleware, new Request(), $GLOBALS['kernel'] ) );

        $this->assertSame( $middleware, PhpSfContext::current()->getMiddleware() );
    }

    public function testGetRequest(): void
    {
        $request = new Request( [], [], [], [], [], [ 'REQUEST_URI' => '/test' ] );
        PhpSfContext::set( new PhpSfContext( (object)[], [], $request, $GLOBALS['kernel'] ) );

        $this->assertSame( $request, PhpSfContext::current()->getRequest() );
    }

    public function testGetKernel(): void
    {
        $kernel = $GLOBALS['kernel'];
        PhpSfContext::set( new PhpSfContext( (object)[], [], new Request(), $kernel ) );

        $this->assertSame( $kernel, PhpSfContext::current()->getKernel() );
    }


    private function ctx( string $url ): PhpSfContext
    {
        return new PhpSfContext( (object)[ 'url' => $url ], [], new Request(), $GLOBALS['kernel'] );
    }

    private function reset(): void
    {
        ( new ReflectionClass( PhpSfContext::class ) )->getProperty( 'current' )->setValue( null, null );
    }

}
