<?php

namespace Symfony\Bundle\MakerBundle\Doctrine;

use Symfony\Bundle\MakerBundle\Str;

/**
 * @internal
 */
abstract class BaseCollectionRelation extends BaseRelation
{
    abstract public function getOrphanRemoval(): bool;

    abstract public function getTargetSetterMethodName(): string;

    public function getAdderMethodName(): string
    {
        return 'add' . Str::asCamelCase( Str::pluralCamelCaseToSingular( $this->getPropertyName() ) );
    }

    public function getRemoverMethodName(): string
    {
        return 'remove' . Str::asCamelCase( Str::pluralCamelCaseToSingular( $this->getPropertyName() ) );
    }
}
