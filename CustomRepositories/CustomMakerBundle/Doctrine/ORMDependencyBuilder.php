<?php

namespace Symfony\Bundle\MakerBundle\Doctrine;

use Doctrine\ORM\Mapping\Column;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;

/**
 * @internal
 */
final class ORMDependencyBuilder
{
    /**
     * Central method to add dependencies needed for Doctrine ORM.
     */
    public static function buildDependencies( DependencyBuilder $dependencies ): void
    {
        $classes = [
            // guarantee DoctrineBundle
            DoctrineBundle::class,
            // guarantee ORM
            Column::class,
        ];

        foreach ( $classes as $class ) {
            $dependencies->addClassDependency(
                $class,
                'orm'
            );
        }
    }
}
