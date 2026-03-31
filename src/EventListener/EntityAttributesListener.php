<?php declare( strict_types=1 );

namespace PHP_SF\System\EventListener;

use Doctrine\DBAL\Driver\AbstractPostgreSQLDriver;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHP_SF\System\Classes\Exception\InvalidEntityConfigurationException;


/**
 * Enforces that every concrete entity declares:
 * - `repositoryClass` in {@link \Doctrine\ORM\Mapping\Entity}
 * - `schema` in {@link \Doctrine\ORM\Mapping\Table}
 *
 * Fires during Doctrine metadata loading (cache-cold path only), so it catches
 * misconfigured entities at cache:warmup / first dev request — not on every
 * production request once the metadata cache is warm.
 */
final class EntityAttributesListener
{

    public function loadClassMetadata( LoadClassMetadataEventArgs $args ): void
    {
        $metadata = $args->getClassMetadata();

        // Skip MappedSuperclass (AbstractEntity itself) and embeddables
        if ( $metadata->isMappedSuperclass || $metadata->isEmbeddedClass )
            return;

        // Only enforce on classes that extend AbstractEntity
        if ( is_a( $metadata->getName(), AbstractEntity::class, true ) === false )
            return;

        $class = $metadata->getName();

        if ( empty( $metadata->customRepositoryClassName ) )
            throw new InvalidEntityConfigurationException(
                sprintf( 'Entity "%s" must declare repositoryClass in #[ORM\\Entity].', $class )
            );

        if (
            empty( $metadata->table['schema'] ?? null )
            && $args->getObjectManager()->getConnection()->getDriver() instanceof AbstractPostgreSQLDriver
        )
            throw new InvalidEntityConfigurationException(
                sprintf( 'Entity "%s" must declare schema in #[ORM\\Table].', $class )
            );
    }

}
