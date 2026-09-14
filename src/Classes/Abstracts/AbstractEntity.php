<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Abstracts;

use PHP_SF\System\Traits\ModelProperty\ModelPropertyIdTrait;

/**
 * Default PHP-SF entity base — auto-incrementing integer primary key.
 *
 * Inherits validation, JSON serialization, repository helpers, and lifecycle
 * callback wiring from {@see AbstractEntityContract}. Most entities should keep
 * extending this class.
 *
 * For non-integer primary keys (UUID v4/v7, ULID, manually-assigned string,
 * composite key) extend {@see AbstractEntityContract} directly and declare
 * your own `#[ORM\Id]` field — see the wiki page on entities for a full
 * example.
 */
abstract class AbstractEntity extends AbstractEntityContract
{
    use ModelPropertyIdTrait;


    final public function getId(): int
    {
        return $this->id;
    }
}
