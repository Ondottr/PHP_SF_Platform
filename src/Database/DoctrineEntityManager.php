<?php /** @noinspection PhpMissingParentCallCommonInspection */
declare( strict_types=1 );

/*
 * Copyright © 2018-2022, Nations Original Sp. z o.o. <contact@nations-original.com>
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

use Exception;
use BadMethodCallException;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;


class DoctrineEntityManager extends EntityManager
{

    public static bool $cacheEnabled = true;
    /**
     * @var array{string}
     */
    private static array                 $dbRequestsList    = [];
    private static DoctrineEntityManager $entityManager;
    private static array                 $entityDirectories = [];

    public static function getEntityManager( bool $cacheEnabled = true ): DoctrineEntityManager
    {
        self::$cacheEnabled = $cacheEnabled;

        if ( !isset( self::$entityManager ) )
            self::setEntityManager();

        return self::$entityManager;
    }

    /**
     * @throws ORMException
     */
    private static function setEntityManager(): void
    {
        $config = Setup::createAnnotationMetadataConfiguration(
                                       self::getEntityDirectories(),
                                       DEV_MODE,
            useSimpleAnnotationReader: false
        );

        if ( !$config->getMetadataDriverImpl() ) {
            throw MissingMappingDriverImplementation::create();
        }

        $connection = self::createConnection( [ 'url' => env( 'DATABASE_URL' ) ], $config );

        self::$entityManager = new DoctrineEntityManager( $connection, $config, $connection->getEventManager() );
    }

    public static function getEntityDirectories(): array
    {
        return self::$entityDirectories;
    }

    public static function addEntityDirectory( string $entityDirectories ): void
    {
        self::$entityDirectories[] = $entityDirectories;
    }

    /**
     * @return array{string}
     */
    public static function getDbRequestsList(): array
    {
        return self::$dbRequestsList;
    }

    public static function addDBRequest( string $dbRequestsList ): void
    {
        self::$dbRequestsList[] = $dbRequestsList;
    }

    public static function disableCache(): void
    {
        self::$cacheEnabled = false;
    }

    public function createQuery( $dql = '' ): Query
    {
        $query = new Query( $this );

        if ( !empty( $dql ) ) {
            $query->setDQL( $dql );
        }

        return $query;
    }

    public function executeSeveral( \Doctrine\ORM\Query|Query ...$queries ): bool
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

    public function flushUsingTransaction( AbstractEntity ...$entities ): void
    {
        try {
            em()->beginTransaction();

            foreach ( $entities as $entity )
                em()->flush( $entity );

            em()->commit();
        } catch ( Exception $e ) {
            em()->rollback();

            throw $e;
        }
    }

    /**
     * @param AbstractEntity|null $entity
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function flush( $entity = null ): void
    {
        if ( $entity instanceof AbstractEntity === false ) {
            throw new BadMethodCallException( '`Flush` method must be called with entity object!' );
        }

        parent::flush( $entity );
    }

}
