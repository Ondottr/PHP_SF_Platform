<?php declare( strict_types=1 );
/*
 * Copyright © 2018-2026, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 * granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 * INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 * LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 * RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 * TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

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
