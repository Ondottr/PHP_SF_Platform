<?php declare(strict_types=1);

namespace PHP_SF\Tests\Unit\Traits;

use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionUnionType;

/**
 * Verifies {@see \PHP_SF\System\Traits\EntityRepositoriesTrait::find()} accepts
 * both integer and string PKs (the widening done in v4.2.0 to support custom
 * UUID/ULID PK entities). We don't run the actual `rep()->find()` path here —
 * that needs a real entity manager — but we can confirm the type signature
 * accepts both shapes.
 */
#[CoversClass(\PHP_SF\System\Traits\EntityRepositoriesTrait::class)]
final class EntityRepositoriesTraitTest extends TestCase
{
    public function testFindSignatureIsPublicStatic(): void
    {
        $rc = new ReflectionMethod(\PHP_SF\System\Traits\EntityRepositoriesTrait::class, 'find');

        $this->assertTrue($rc->isStatic());
        $this->assertTrue($rc->isPublic());

        $params = $rc->getParameters();
        $this->assertCount(1, $params);
    }

    public function testFindParameterIsIntOrStringUnion(): void
    {
        $rc = new ReflectionMethod(\PHP_SF\System\Traits\EntityRepositoriesTrait::class, 'find');

        $type = $rc->getParameters()[0]->getType();
        $this->assertInstanceOf(ReflectionUnionType::class, $type);

        $names = array_map(static fn ($t) => (string) $t, $type->getTypes());
        sort($names);
        $this->assertSame(['int', 'string'], $names);
    }
}

final class ConcreteIntEntity extends AbstractEntity {}
