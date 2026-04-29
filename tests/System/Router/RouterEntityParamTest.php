<?php declare( strict_types=1 );

namespace PHP_SF\Tests\System\Router;

use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHP_SF\System\Classes\Exception\RouteParameterException;
use PHP_SF\System\Router;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

// ---------------------------------------------------------------------------
// Stub entity — self-contained, no Doctrine ORM setup required.
// Only properties matter; checkMethodParameterType uses ReflectionClass::hasProperty().
// ---------------------------------------------------------------------------

class StubEntity extends AbstractEntity
{
    protected ?string $slug  = null;
    protected ?int    $score = null;
}


// ---------------------------------------------------------------------------
// Expose protected setRouteParameters() and private $routeParams for testing.
// ---------------------------------------------------------------------------

final class TestableRouter extends Router
{
    public static function callSetRouteParameters(): void
    {
        static::setRouteParameters();
    }

    public static function setRouteParams( array $params ): void
    {
        ( new ReflectionProperty( Router::class, 'routeParams' ) )->setValue( null, $params );
    }

    public static function getRouteParams(): array
    {
        return ( new ReflectionProperty( Router::class, 'routeParams' ) )->getValue( null );
    }
}


// ---------------------------------------------------------------------------
// Controller fixture — method signatures that map to test scenarios.
// ---------------------------------------------------------------------------

final class StubController
{
    public function actionWithEntity( StubEntity $entity ): void {}
    public function actionWithNullableEntity( ?StubEntity $entity ): void {}
    public function actionWithPlainTypes( int $count, string $name ): void {}
    public function actionWithMixed( StubEntity $entity, int $page ): void {}
}


final class RouterEntityParamTest extends TestCase
{

    private array $savedRouteParams;
    private mixed $savedCurrentRoute;


    protected function setUp(): void
    {
        $ref                    = new ReflectionProperty( Router::class, 'routeParams' );
        $this->savedRouteParams = $ref->getValue( null );
        $this->savedCurrentRoute = Router::$currentRoute;
    }

    protected function tearDown(): void
    {
        ( new ReflectionProperty( Router::class, 'routeParams' ) )->setValue( null, $this->savedRouteParams );
        Router::$currentRoute = $this->savedCurrentRoute;
    }


    // -----------------------------------------------------------------------
    // checkMethodParameterType — field existence validation at route-registration time
    // -----------------------------------------------------------------------

    public function testValidEntityFieldRaisesNoException(): void
    {
        // 'slug' is declared on StubEntity — should pass silently
        $this->callCheckMethodParameterType( StubEntity::class, 'entity', $this->routeData( [ 'slug' ] ), 0 );
        $this->expectNotToPerformAssertions();
    }

    public function testInheritedFieldIdIsValid(): void
    {
        // 'id' comes from ModelPropertyIdTrait via AbstractEntity
        $this->callCheckMethodParameterType( StubEntity::class, 'entity', $this->routeData( [ 'id' ] ), 0 );
        $this->expectNotToPerformAssertions();
    }

    public function testInvalidEntityFieldThrowsRouteParameterException(): void
    {
        $this->expectException( RouteParameterException::class );
        $this->expectExceptionMessageMatches( '/nonExistentField/' );

        $this->callCheckMethodParameterType(
            StubEntity::class, 'entity', $this->routeData( [ 'nonExistentField' ] ), 0
        );
    }

    public function testExceptionMessageContainsEntityClass(): void
    {
        $this->expectException( RouteParameterException::class );
        $this->expectExceptionMessageMatches( '/' . preg_quote( StubEntity::class, '/' ) . '/' );

        $this->callCheckMethodParameterType(
            StubEntity::class, 'entity', $this->routeData( [ 'badField' ] ), 0
        );
    }

    public function testPlainTypesSkipEntityFieldCheck(): void
    {
        // Plain types should pass regardless of URL placeholder name
        foreach ( [ 'int', 'float', 'string' ] as $type ) {
            $this->callCheckMethodParameterType( $type, 'param', $this->routeData( [ 'anything' ] ), 0 );
        }
        $this->expectNotToPerformAssertions();
    }

    public function testNoUrlPlaceholderAtIndexSkipsCheck(): void
    {
        // routeParams is shorter than paramIndex — no placeholder to validate against
        $this->callCheckMethodParameterType( StubEntity::class, 'entity', $this->routeData( [] ), 0 );
        $this->expectNotToPerformAssertions();
    }

    public function testSecondParamValidatedAtCorrectIndex(): void
    {
        // Two URL params; second maps to StubEntity — validate 'score' at index 1
        $data = $this->routeData( [ 'id', 'score' ] );

        $this->callCheckMethodParameterType( 'int', 'page', $data, 0 );           // plain — no check
        $this->callCheckMethodParameterType( StubEntity::class, 'entity', $data, 1 ); // 'score' is valid

        $this->expectNotToPerformAssertions();
    }

    public function testSecondParamWithBadFieldThrows(): void
    {
        $this->expectException( RouteParameterException::class );

        $data = $this->routeData( [ 'id', 'doesNotExist' ] );
        $this->callCheckMethodParameterType( StubEntity::class, 'entity', $data, 1 );
    }


    // -----------------------------------------------------------------------
    // setRouteParameters — positional matching, plain types
    // -----------------------------------------------------------------------

    public function testPlainTypePositionalMatching(): void
    {
        // URL placeholders 'qty' and 'title' — param names differ intentionally
        TestableRouter::setRouteParams( [ 'qty' => '5', 'title' => 'hello' ] );
        Router::$currentRoute = (object)[
            'class'  => StubController::class,
            'method' => 'actionWithPlainTypes',
        ];

        TestableRouter::callSetRouteParameters();

        $resolved = TestableRouter::getRouteParams();

        // Keys must be re-keyed to method param names
        $this->assertArrayHasKey( 'count', $resolved );
        $this->assertArrayHasKey( 'name', $resolved );
        $this->assertArrayNotHasKey( 'qty', $resolved );
        $this->assertArrayNotHasKey( 'title', $resolved );

        // Values must be cast to declared types
        $this->assertSame( 5, $resolved['count'] );
        $this->assertSame( 'hello', $resolved['name'] );
    }

    public function testCountMismatchThrowsRouteParameterException(): void
    {
        // 2 URL params but method has 1 param
        TestableRouter::setRouteParams( [ 'a' => '1', 'b' => '2' ] );
        Router::$currentRoute = (object)[
            'class'  => StubController::class,
            'method' => 'actionWithEntity',
        ];

        $this->expectException( RouteParameterException::class );

        TestableRouter::callSetRouteParameters();
    }

    public function testEmptyRouteParamsSkipsResolution(): void
    {
        TestableRouter::setRouteParams( [] );
        Router::$currentRoute = (object)[
            'class'  => StubController::class,
            'method' => 'actionWithPlainTypes',
        ];

        TestableRouter::callSetRouteParameters();

        $this->assertSame( [], TestableRouter::getRouteParams() );
    }


    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function routeData( array $routeParams ): object
    {
        return (object)[
            'class'       => StubController::class,
            'method'      => 'actionWithEntity',
            'routeParams' => $routeParams,
        ];
    }

    private function callCheckMethodParameterType( string $type, string $propertyName, object $data, int $paramIndex ): void
    {
        $method = new ReflectionMethod( Router::class, 'checkMethodParameterType' );
        $method->invoke( null, $type, $propertyName, $data, $paramIndex );
    }

}
