<?php /** @noinspection ProhibitedClassExtendInspection @noinspection PhpMissingParentCallCommonInspection */
declare( strict_types=1 );
/*
 * Copyright © 2018-2024, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace PHP_SF\System\Database;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Query;
use Exception;
use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use Symfony\Component\Cache\Adapter\RedisAdapter;

final class DoctrineEntityManager extends EntityManager
{

    private static self|null $entityManager = null;
    /**
     * @var array<string>
     */
    private static array $entityDirectories = [];

    public static function invalidateEntityManager(): void
    {
        self::$entityManager = null;
    }

    public static function getEntityManager(): self
    {
        if ( self::$entityManager === null )
            self::setEntityManager();

        return self::$entityManager;
    }

    private static function setEntityManager(): void
    {
        $ra = new RedisAdapter( Redis::getClient() );
        $config = ORMSetup::createAttributeMetadataConfiguration(
            self::getEntityDirectories(), DEV_MODE,
            __DIR__ . '/../../../var/cache/prod/doctrine/orm/Proxies',
            $ra
        );

        $config->setProxyNamespace( 'Proxies' );

        $config->setMetadataCache( $ra );
        $config->setHydrationCache( $ra );

        if ( $config->getMetadataDriverImpl() === false )
            throw MissingMappingDriverImplementation::create();


        $connection = DriverManager::getConnection( [ 'url' => env( 'DATABASE_URL' ) ], $config );

        self::$entityManager = new self( $connection, $config );
    }

    public static function getEntityDirectories(): array
    {
        return self::$entityDirectories;
    }

    public static function addEntityDirectory( string $entityDirectories ): void
    {
        self::$entityDirectories[] = $entityDirectories;
    }

    public function createQuery($dql = '' ): Query
    {
        $query = new Query( $this );

        if ( empty( $dql ) === false )
            $query->setDQL( $dql );

        return $query;
    }

    final public function executeSeveral( Query ...$queries ): bool
    {
        try {
            em()->beginTransaction();

            foreach ( $queries as $query ) {
                $query->execute();
            }

            em()->commit();
        } catch ( Exception $e ) {
            em()->rollBack();

            throw $e;
        }

        return true;
    }

    final public function flushUsingTransaction( AbstractEntity ...$entities ): void
    {
        try {
            em()->beginTransaction();

            if ( empty( $entities ) === false ) {
                foreach ( $entities as $entity )
                    em()->flush( $entity );

            } else
                em()->flush();

            em()->commit();
        } catch ( Exception $e ) {
            em()->rollback();

            throw $e;
        }
    }

    /**
     * @param AbstractEntity|null $entity
     */
    final public function flush( $entity = null ): void
    {
        parent::flush( $entity );
    }

}
