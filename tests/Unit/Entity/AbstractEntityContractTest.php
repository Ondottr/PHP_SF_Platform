<?php declare(strict_types=1);

namespace PHP_SF\Tests\Unit\Entity;

use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping as ORM;
use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHP_SF\System\Classes\Abstracts\AbstractEntityContract;
use PHP_SF\System\Classes\DoctrineLifecycleCallbacks\SetUuidIfEmptyCallback;
use PHP_SF\System\Traits\ModelProperty\ModelPropertyUuidTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Verifies that {@see AbstractEntityContract} exposes the full behavior surface
 * (validation, JSON serialization, factory) and that concrete subclasses can
 * pick any primary-key flavor — integer (the BC path) or UUID / composite.
 *
 * No database is required: we exercise methods directly and inspect the
 * generated Doctrine attribute mapping via reflection.
 */
#[CoversClass(AbstractEntityContract::class)]
#[CoversClass(AbstractEntity::class)]
final class AbstractEntityContractTest extends TestCase
{
    public function testAbstractEntityIsSubclassOfContract(): void
    {
        $this->assertTrue(is_subclass_of(AbstractEntity::class, AbstractEntityContract::class));
    }

    public function testAbstractEntityKeepsIntegerPrimaryKey(): void
    {
        $entity = new ConcreteIntEntity();

        $this->assertInstanceOf(AbstractEntityContract::class, $entity);
        $this->assertTrue(method_exists($entity, 'getId'));
        $this->assertTrue((new ReflectionClass($entity))->getMethod('getId')->hasReturnType());
        $this->assertSame('int', (string) (new ReflectionClass($entity))->getMethod('getId')->getReturnType());
    }

    public function testUuidEntityUsesStringPrimaryKey(): void
    {
        $entity = new ConcreteUuidEntity();

        $this->assertNull($entity->getId());

        $entity->setId('0190e7a5-7c3a-7c2a-9d3e-1f1a2b3c4d5e');
        $this->assertSame('0190e7a5-7c3a-7c2a-9d3e-1f1a2b3c4d5e', $entity->getId());
    }

    public function testCompositeIdEntityExposesBothIds(): void
    {
        $entity = new ConcreteCompositeIdEntity();

        $this->assertSame(0, $entity->getTenantId());
        $this->assertSame('slug-1', $entity->getSlug());

        $entity->setTenantId(7)->setSlug('foo');
        $this->assertSame(7, $entity->getTenantId());
        $this->assertSame('foo', $entity->getSlug());
    }

    public function testAbstractEntityContractDeclaresAbstractGetId(): void
    {
        $rc = new ReflectionClass(AbstractEntityContract::class);

        $this->assertTrue($rc->hasMethod('getId'));
        $this->assertTrue($rc->getMethod('getId')->isAbstract());
    }

    public function testAbstractEntityDeclaresIntegerGetId(): void
    {
        $rc = new ReflectionClass(AbstractEntity::class);

        $method = $rc->getMethod('getId');
        $this->assertSame('int', (string) $method->getReturnType());
    }

    public function testConcreteUuidEntityHasGuidPrimaryKeyColumn(): void
    {
        $rc = new ReflectionClass(ConcreteUuidEntity::class);

        $idProperty = $rc->getProperty('id');
        $idAttributes = $idProperty->getAttributes();

        $columnAttr = null;
        foreach ($idAttributes as $attr) {
            if (ORM\Column::class === $attr->getName()) {
                $columnAttr = $attr;
                break;
            }
        }

        $this->assertNotNull($columnAttr, 'ConcreteUuidEntity::$id must carry an #[ORM\Column] attribute.');

        $args = $columnAttr->getArguments();
        $this->assertSame('guid', $args['type'] ?? null);
    }

    public function testNewFactoryReturnsStaticInstance(): void
    {
        $entity = ConcreteUuidEntity::new();

        $this->assertInstanceOf(ConcreteUuidEntity::class, $entity);
    }

    public function testJsonSerializeIncludesProperties(): void
    {
        $entity = new ConcreteValidatableEntity();
        $entity->setName('Widget');

        $serialized = $entity->jsonSerialize();

        $this->assertIsArray($serialized);
        $this->assertArrayHasKey('name', $serialized);
        $this->assertSame('Widget', $serialized['name']);
    }

    public function testTranslatablePropertyNameFollowsSnakeCaseConvention(): void
    {
        $entity = new ConcreteValidatableEntity();

        $this->assertSame(
            'concrete_validatable_entity.fields.name',
            $entity->getTranslatablePropertyName('name'),
        );
    }

    public function testSetUuidIfEmptyCallbackAssignsUuidWhenIdIsNull(): void
    {
        $entity = new ConcreteUuidEntity();
        $this->assertNull($entity->getId());

        (new SetUuidIfEmptyCallback($entity, new FakeEventArgs()))->callback();

        $assigned = $entity->getId();
        $this->assertIsString($assigned);
        $this->assertTrue(Uuid::isValid($assigned));
    }

    public function testSetUuidIfEmptyCallbackAssignsUuidWhenIdIsEmptyString(): void
    {
        $entity = new ConcreteUuidEntity();
        $entity->setId('');

        (new SetUuidIfEmptyCallback($entity, new FakeEventArgs()))->callback();

        $assigned = $entity->getId();
        $this->assertNotSame('', $assigned);
        $this->assertTrue(Uuid::isValid($assigned));
    }

    public function testSetUuidIfEmptyCallbackPreservesManuallyAssignedId(): void
    {
        $entity = new ConcreteUuidEntity();
        $manual = '0190e7a5-7c3a-7c2a-9d3e-1f1a2b3c4d5e';
        $entity->setId($manual);

        (new SetUuidIfEmptyCallback($entity, new FakeEventArgs()))->callback();

        $this->assertSame($manual, $entity->getId());
    }

    public function testSetUuidIfEmptyCallbackIsSilentlySkippedWhenEntityHasNoSetId(): void
    {
        $entity = new ConcreteNoSetIdEntity();
        $this->assertSame(0, $entity->getId());

        (new SetUuidIfEmptyCallback($entity, new FakeEventArgs()))->callback();

        // No exception thrown, id unchanged.
        $this->assertSame(0, $entity->getId());
    }

    public function testUuidEntityCanRegisterAutoUuidCallbackViaGetLifecycleCallbacks(): void
    {
        $entity = new ConcreteUuidEntity();
        $callbacks = $entity->getLifecycleCallbacks();

        $this->assertArrayHasKey(Events::prePersist, $callbacks);
        $this->assertSame(SetUuidIfEmptyCallback::class, $callbacks[Events::prePersist]);
    }
}


// ---------------------------------------------------------------------------
// Test fixtures — kept inline so this file is self-contained.
// ---------------------------------------------------------------------------

final class ConcreteIntEntity extends AbstractEntity {}

final class ConcreteUuidEntity extends AbstractEntityContract
{
    use ModelPropertyUuidTrait;


    public function getLifecycleCallbacks(): array
    {
        return [Events::prePersist => SetUuidIfEmptyCallback::class];
    }
}

final class ConcreteNoSetIdEntity extends AbstractEntityContract
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    protected int $id = 0;


    public function getId(): int
    {
        return $this->id;
    }
}

/**
 * Stand-in for Doctrine\Common\EventArgs — we never read it in the callback.
 */
final class FakeEventArgs extends \Doctrine\Common\EventArgs {}

final class ConcreteCompositeIdEntity extends AbstractEntityContract
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    protected int $tenantId = 0;

    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    protected string $slug = 'slug-1';


    public function getId(): array
    {
        return ['tenantId' => $this->tenantId, 'slug' => $this->slug];
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function setTenantId(int $v): static
    {
        $this->tenantId = $v;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $v): static
    {
        $this->slug = $v;

        return $this;
    }
}

final class ConcreteValidatableEntity extends AbstractEntityContract
{
    #[Assert\NotBlank]
    protected ?string $name = null;


    public function getId(): int
    {
        return 0;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $v): static
    {
        $this->name = $v;

        return $this;
    }
}
