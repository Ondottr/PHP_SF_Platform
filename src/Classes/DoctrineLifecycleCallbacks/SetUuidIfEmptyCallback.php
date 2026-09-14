<?php /** @noinspection PhpUnused */
declare(strict_types=1);

namespace PHP_SF\System\Classes\DoctrineLifecycleCallbacks;

use PHP_SF\System\Classes\Abstracts\AbstractDoctrineLifecycleCallback;
use Symfony\Component\Uid\Uuid;

/**
 * Auto-assigns a UUID v4 primary key on `prePersist` when none was set manually.
 *
 * Pairs with `ModelPropertyUuidTrait` — see the wiki's "Custom abstract entities
 * and alternative primary keys" section for a full wiring example.
 *
 * ```php
 * // App/DoctrineLifecycleCallbacks/OrderPrePersistCallback.php — typical user wiring
 * use Doctrine\ORM\Events;
 * use PHP_SF\System\Classes\Abstracts\AbstractEntityContract;
 *
 * abstract class AbstractUuidEntity extends AbstractEntityContract
 * {
 *     use ModelPropertyUuidTrait;
 *
 *     public function getLifecycleCallbacks(): array
 *     {
 *         return [Events::prePersist => SetUuidIfEmptyCallback::class];
 *     }
 * }
 *
 * class Order extends AbstractUuidEntity { /* ... *\/ }
 * ```
 *
 * The callback is intentionally generic — it does not require the entity to
 * extend any specific class. It only assumes the entity has `getId()` returning
 * `?string` (so `''` is treated as empty too) and a `setId(string $id): static`
 * method.
 */
final class SetUuidIfEmptyCallback extends AbstractDoctrineLifecycleCallback
{
    public function callback(): void
    {
        // `symfony/uid` is an optional dep provided by the consuming application.
        // The phpstan baseline suppresses the class-not-found warning; runtime
        // requires the user to install the package.
        $uuid = Uuid::v4()->toRfc4122();

        $entity = $this->entity;

        // Cast to mixed then narrow via runtime checks — entities using this
        // callback declare their own PK shape, so we cannot rely on a static type
        // narrower that fits every user-land UUID entity.
        $currentId = $entity->getId();

        if (is_string($currentId) && '' !== $currentId) {
            return;
        }

        if (!method_exists($entity, 'setId')) {
            return;
        }

        $entity->setId($uuid);
    }
}
