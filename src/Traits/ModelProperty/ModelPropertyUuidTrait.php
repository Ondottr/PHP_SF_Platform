<?php declare(strict_types=1);

namespace PHP_SF\System\Traits\ModelProperty;

use Doctrine\ORM\Mapping as ORM;

/**
 * Optional helper for entities whose primary key is a string UUID stored in a
 * `guid`/`uuid` column. Doctrine 3 removed the built-in `UUID` generation
 * strategy, so callers must generate the UUID application-side.
 *
 * Intended to be used together with {@see \PHP_SF\System\Classes\Abstracts\AbstractEntityContract}
 * — not on top of {@see \PHP_SF\System\Classes\Abstracts\AbstractEntity}, which
 * already declares an integer `id` via `ModelPropertyIdTrait`.
 *
 * Pair it with {@see \PHP_SF\System\Classes\DoctrineLifecycleCallbacks\SetUuidIfEmptyCallback}
 * to auto-assign a UUID v4 on `prePersist` whenever `$id` is still null/empty:
 *
 * ```php
 * use Doctrine\ORM\Events;
 * use PHP_SF\System\Classes\Abstracts\AbstractEntityContract;
 * use PHP_SF\System\Classes\DoctrineLifecycleCallbacks\SetUuidIfEmptyCallback;
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
 * class Order extends AbstractUuidEntity
 * {
 *     public static function create(string $customerName): self
 *     {
 *         # No need to setId() — the prePersist callback assigns a UUID v4
 *         # automatically if $id is null/empty. Calling setId() explicitly
 *         # still wins.
 *         $order = new self();
 *         $order->setCustomerName($customerName);
 *         return $order;
 *     }
 * }
 * ```
 */
trait ModelPropertyUuidTrait
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    protected ?string $id = null;


    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }
}
