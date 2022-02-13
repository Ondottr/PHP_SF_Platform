<?php

namespace Symfony\Bundle\MakerBundle\Doctrine;

use Exception;
use Symfony\Bundle\MakerBundle\Str;

/**
 * @internal
 */
final class RelationOneToMany extends BaseCollectionRelation
{
    private $orphanRemoval;

    public function getOrphanRemoval(): bool
    {
        return $this->orphanRemoval;
    }

    public function setOrphanRemoval( $orphanRemoval ): self
    {
        $this->orphanRemoval = $orphanRemoval;

        return $this;
    }

    public function getTargetGetterMethodName(): string
    {
        return 'get' . Str::asCamelCase( $this->getTargetPropertyName() );
    }

    public function getTargetSetterMethodName(): string
    {
        return 'set' . Str::asCamelCase( $this->getTargetPropertyName() );
    }

    public function isOwning(): bool
    {
        return false;
    }

    public function isMapInverseRelation(): bool
    {
        throw new Exception( 'OneToMany IS the inverse side!' );
    }
}
