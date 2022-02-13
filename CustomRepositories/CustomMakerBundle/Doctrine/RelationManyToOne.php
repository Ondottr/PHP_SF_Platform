<?php

namespace Symfony\Bundle\MakerBundle\Doctrine;

/**
 * @internal
 */
final class RelationManyToOne extends BaseSingleRelation
{
    public function isOwning(): bool
    {
        return true;
    }
}
