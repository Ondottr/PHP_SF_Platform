<?php declare(strict_types=1);

namespace PHP_SF\Tests\System\Router;

use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHP_SF\System\Classes\Exception\RouteParameterException;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

// ---------------------------------------------------------------------------
// Stub entity — self-contained, no Doctrine ORM setup required.
// Only properties matter; checkMethodParameterType uses ReflectionClass::hasProperty().
// ---------------------------------------------------------------------------

class StubEntity extends AbstractEntity
{
    protected ?string $slug = null;
    protected ?int $score = null;
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

    /**
     * @param array<string, string> $params placeholder name => URL value, in URL order
     */
    public static function setRouteParams(array $params): void
    {
        $urlParams = [];
        foreach ($params as $name => $value) {
            $urlParams[] = ['name' => $name, 'value' => $value];
        }

        self::setUrlParams($urlParams);
    }

    /**
     * Positional shape, needed when one placeholder name repeats in the URL.
     *
     * @param list<array{name: string, value: string}> $urlParams
     */
    public static function setUrlParams(array $urlParams): void
    {
        (new ReflectionProperty(Router::class, 'urlParams'))->setValue(null, $urlParams);
        (new ReflectionProperty(Router::class, 'routeParams'))->setValue(null, []);
    }

    public static function getRouteParams(): array
    {
        return (new ReflectionProperty(Router::class, 'routeParams'))->getValue(null);
    }

    public static function callRoutesFromController(string $namespace, string $fileName): void
    {
        static::routesFromController($namespace, $fileName);
    }

    public static function resetRoutesList(): void
    {
        (new ReflectionProperty(Router::class, 'routesList'))->setValue(null, []);
    }
}

// ---------------------------------------------------------------------------
// Controller fixture — method signatures that map to test scenarios.
// ---------------------------------------------------------------------------

final class StubController
{
    public function actionWithEntity(StubEntity $entity): void {}

    public function actionWithNullableEntity(?StubEntity $entity): void {}

    public function actionWithPlainTypes(int $count, string $name): void {}

    public function actionWithMixed(StubEntity $entity, int $page): void {}

    public function actionWithTwoIds(int $user, int $payment): void {}
}

// ---------------------------------------------------------------------------
// Route fixtures for registration-time parsing. Bodies are never invoked.
// ---------------------------------------------------------------------------

final class StubCrudController
{
    #[Route(url: 'crud/users/{id}/payment/{id}', httpMethod: 'GET', name: 'stub_crud_repeated_ids')]
    public function repeatedIds(?StubEntity $user, ?StubEntity $payment): Response {}

    #[Route(url: 'crud/users/{id}/payment/{paymentId}', httpMethod: 'GET', name: 'stub_crud_distinct_ids')]
    public function distinctIds(?StubEntity $user, ?StubEntity $payment): Response {}
}

final class StubArityMismatchController
{
    #[Route(url: 'crud/users/{id}/payment/{id}', httpMethod: 'GET', name: 'stub_crud_arity_mismatch')]
    public function tooFewParameters(?StubEntity $user): Response {}
}

final class RouterEntityParamTest extends TestCase
{
    private array $savedRouteParams;

    private array $savedUrlParams;

    private mixed $savedCurrentRoute;


    // -----------------------------------------------------------------------
    // checkMethodParameterType — field existence validation at route-registration time
    // -----------------------------------------------------------------------

    public function testValidEntityFieldRaisesNoException(): void
    {
        // 'slug' is declared on StubEntity — should pass silently
        $this->callCheckMethodParameterType(StubEntity::class, 'entity', $this->routeData(['slug']), 0);
        $this->expectNotToPerformAssertions();
    }

    public function testInheritedFieldIdIsValid(): void
    {
        // 'id' comes from ModelPropertyIdTrait via AbstractEntity
        $this->callCheckMethodParameterType(StubEntity::class, 'entity', $this->routeData(['id']), 0);
        $this->expectNotToPerformAssertions();
    }

    public function testInvalidEntityFieldThrowsRouteParameterException(): void
    {
        $this->expectException(RouteParameterException::class);
        $this->expectExceptionMessageMatches('/nonExistentField/');

        $this->callCheckMethodParameterType(
            StubEntity::class,
            'entity',
            $this->routeData(['nonExistentField']),
            0,
        );
    }

    public function testExceptionMessageContainsEntityClass(): void
    {
        $this->expectException(RouteParameterException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote(StubEntity::class, '/') . '/');

        $this->callCheckMethodParameterType(
            StubEntity::class,
            'entity',
            $this->routeData(['badField']),
            0,
        );
    }

    public function testPlainTypesSkipEntityFieldCheck(): void
    {
        // Plain types should pass regardless of URL placeholder name
        foreach (['int', 'float', 'string'] as $type) {
            $this->callCheckMethodParameterType($type, 'param', $this->routeData(['anything']), 0);
        }
        $this->expectNotToPerformAssertions();
    }

    public function testNoUrlPlaceholderAtIndexSkipsCheck(): void
    {
        // routeParams is shorter than paramIndex — no placeholder to validate against
        $this->callCheckMethodParameterType(StubEntity::class, 'entity', $this->routeData([]), 0);
        $this->expectNotToPerformAssertions();
    }

    public function testSecondParamValidatedAtCorrectIndex(): void
    {
        // Two URL params; second maps to StubEntity — validate 'score' at index 1
        $data = $this->routeData(['id', 'score']);

        $this->callCheckMethodParameterType('int', 'page', $data, 0);           // plain — no check
        $this->callCheckMethodParameterType(StubEntity::class, 'entity', $data, 1); // 'score' is valid

        $this->expectNotToPerformAssertions();
    }

    public function testSecondParamWithBadFieldThrows(): void
    {
        $this->expectException(RouteParameterException::class);

        $data = $this->routeData(['id', 'doesNotExist']);
        $this->callCheckMethodParameterType(StubEntity::class, 'entity', $data, 1);
    }

    // -----------------------------------------------------------------------
    // setRouteParameters — positional matching, plain types
    // -----------------------------------------------------------------------

    public function testPlainTypePositionalMatching(): void
    {
        // URL placeholders 'qty' and 'title' — param names differ intentionally
        TestableRouter::setRouteParams(['qty' => '5', 'title' => 'hello']);
        Router::$currentRoute = (object) [
            'class' => StubController::class,
            'method' => 'actionWithPlainTypes',
        ];

        TestableRouter::callSetRouteParameters();

        $resolved = TestableRouter::getRouteParams();

        // Keys must be re-keyed to method param names
        $this->assertArrayHasKey('count', $resolved);
        $this->assertArrayHasKey('name', $resolved);
        $this->assertArrayNotHasKey('qty', $resolved);
        $this->assertArrayNotHasKey('title', $resolved);

        // Values must be cast to declared types
        $this->assertSame(5, $resolved['count']);
        $this->assertSame('hello', $resolved['name']);
    }

    public function testCountMismatchThrowsRouteParameterException(): void
    {
        // 2 URL params but method has 1 param
        TestableRouter::setRouteParams(['a' => '1', 'b' => '2']);
        Router::$currentRoute = (object) [
            'class' => StubController::class,
            'method' => 'actionWithEntity',
        ];

        $this->expectException(RouteParameterException::class);

        TestableRouter::callSetRouteParameters();
    }

    public function testEmptyRouteParamsSkipsResolution(): void
    {
        TestableRouter::setRouteParams([]);
        Router::$currentRoute = (object) [
            'class' => StubController::class,
            'method' => 'actionWithPlainTypes',
        ];

        TestableRouter::callSetRouteParameters();

        $this->assertSame([], TestableRouter::getRouteParams());
    }

    // -----------------------------------------------------------------------
    // Repeated placeholder names — /crud/users/{id}/payment/{id}
    // -----------------------------------------------------------------------

    public function testRepeatedPlaceholderNameKeepsBothValues(): void
    {
        // Both URL segments are named {id}; binding is positional, so neither is lost
        TestableRouter::setUrlParams([
            ['name' => 'id', 'value' => '7'],
            ['name' => 'id', 'value' => '42'],
        ]);
        Router::$currentRoute = (object) [
            'class' => StubController::class,
            'method' => 'actionWithTwoIds',
        ];

        TestableRouter::callSetRouteParameters();

        $this->assertSame(['user' => 7, 'payment' => 42], TestableRouter::getRouteParams());
    }

    public function testDistinctKeyShapedPlaceholdersKeepBothValues(): void
    {
        // Same route shape written as /crud/users/{id}/payment/{paymentId}
        TestableRouter::setUrlParams([
            ['name' => 'id', 'value' => '7'],
            ['name' => 'paymentId', 'value' => '42'],
        ]);
        Router::$currentRoute = (object) [
            'class' => StubController::class,
            'method' => 'actionWithTwoIds',
        ];

        TestableRouter::callSetRouteParameters();

        $this->assertSame(['user' => 7, 'payment' => 42], TestableRouter::getRouteParams());
    }

    public function testRepeatedPlaceholderCountMismatchStillThrows(): void
    {
        TestableRouter::setUrlParams([
            ['name' => 'id', 'value' => '7'],
            ['name' => 'id', 'value' => '42'],
        ]);
        Router::$currentRoute = (object) [
            'class' => StubController::class,
            'method' => 'actionWithEntity',
        ];

        $this->expectException(RouteParameterException::class);

        TestableRouter::callSetRouteParameters();
    }

    // -----------------------------------------------------------------------
    // resolveEntityLookupField — which entity property a placeholder maps to
    // -----------------------------------------------------------------------

    #[DataProvider('lookupFieldProvider')]
    public function testResolveEntityLookupField(string $placeholder, ?string $expected): void
    {
        $this->assertSame($expected, $this->callResolveEntityLookupField($placeholder));
    }

    public static function lookupFieldProvider(): array
    {
        return [
            'declared property' => ['slug', 'slug'],
            'inherited id property' => ['id', 'id'],
            'camelCase foreign key' => ['paymentId', 'id'],
            'snake_case foreign key' => ['payment_id', 'id'],
            'typo in property name is not silently degraded' => ['slgu', null],
            'unknown non-key placeholder' => ['whatever', null],
        ];
    }

    // -----------------------------------------------------------------------
    // Route registration — both URL spellings of the same CRUD route
    // -----------------------------------------------------------------------

    public function testBothPlaceholderSpellingsRegister(): void
    {
        $savedRoutesList = (new ReflectionProperty(Router::class, 'routesList'))->getValue(null);

        try {
            TestableRouter::resetRoutesList();
            TestableRouter::callRoutesFromController(__NAMESPACE__, 'StubCrudController');

            $routes = Router::getRoutesList();

            $this->assertSame(['id', 'id'], $routes['stub_crud_repeated_ids']['routeParams']);
            $this->assertSame(['id', 'paymentId'], $routes['stub_crud_distinct_ids']['routeParams']);
        } finally {
            (new ReflectionProperty(Router::class, 'routesList'))->setValue(null, $savedRoutesList);
        }
    }

    public function testArityMismatchThrowsAtRegistration(): void
    {
        $savedRoutesList = (new ReflectionProperty(Router::class, 'routesList'))->getValue(null);

        try {
            TestableRouter::resetRoutesList();

            $this->expectException(RouteParameterException::class);

            TestableRouter::callRoutesFromController(__NAMESPACE__, 'StubArityMismatchController');
        } finally {
            (new ReflectionProperty(Router::class, 'routesList'))->setValue(null, $savedRoutesList);
        }
    }

    public function testKeyShapedPlaceholderPassesRegistrationCheck(): void
    {
        // {paymentId} is not a StubEntity property, but falls back to `id`
        $this->callCheckMethodParameterType(StubEntity::class, 'entity', $this->routeData(['paymentId']), 0);
        $this->expectNotToPerformAssertions();
    }

    protected function setUp(): void
    {
        $this->savedRouteParams = (new ReflectionProperty(Router::class, 'routeParams'))->getValue(null);
        $this->savedUrlParams = (new ReflectionProperty(Router::class, 'urlParams'))->getValue(null);
        $this->savedCurrentRoute = Router::$currentRoute;
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Router::class, 'routeParams'))->setValue(null, $this->savedRouteParams);
        (new ReflectionProperty(Router::class, 'urlParams'))->setValue(null, $this->savedUrlParams);
        Router::$currentRoute = $this->savedCurrentRoute;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function routeData(array $routeParams): object
    {
        return (object) [
            'class' => StubController::class,
            'method' => 'actionWithEntity',
            'routeParams' => $routeParams,
        ];
    }

    private function callCheckMethodParameterType(string $type, string $propertyName, object $data, int $paramIndex): void
    {
        $method = new ReflectionMethod(Router::class, 'checkMethodParameterType');
        $method->invoke(null, $type, $propertyName, $data, $paramIndex);
    }

    private function callResolveEntityLookupField(string $placeholder): ?string
    {
        $method = new ReflectionMethod(Router::class, 'resolveEntityLookupField');

        return $method->invoke(null, StubEntity::class, $placeholder);
    }
}
