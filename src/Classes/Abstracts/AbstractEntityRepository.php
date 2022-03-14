<?php declare( strict_types=1 );

/*
 * Copyright © 2018-2022, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 * granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 * INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 * LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 * RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 * TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace PHP_SF\System\Classes\Abstracts;

use Doctrine\ORM\EntityRepository;
use PHP_SF\System\Database\DoctrineEntityManager;


abstract class AbstractEntityRepository extends EntityRepository
{

    final public function find( $id, $lockMode = null, $lockVersion = null ): AbstractEntity|null
    {
        $entityClass = static::getEntityClass();
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if ( $entityClass instanceof AbstractEntity ) {
            //
        }

        $queryName = sprintf( 'repository:one:%s:%s', $entityClass::getClassName(), $id );

        if ( DoctrineEntityManager::$cacheEnabled === false ) {
            $result = parent::find( $id, $lockMode, $lockVersion );

            DoctrineEntityManager::addDBRequest( $queryName );

            return $result;
        }

        $key = sprintf( '%s:cache:%s', SERVER_NAME, $queryName );

        if ( ( $cache = rc()->get( $key ) ) === null ) {
            $entity = parent::find( $id, $lockMode, $lockVersion );
            DoctrineEntityManager::addDBRequest( $queryName );

            $entityClass::setForceSerialise( true );

            $cache = j_encode( $entity );

            $entityClass::setForceSerialise( false );

            rc()->set( $key, $cache );

            return $entity;
        }

        return $entityClass::createFromParams( j_decode( $cache ) );
    }

    abstract protected static function getEntityClass(): string;

    /**
     * @return array<AbstractEntity>
     */
    final public function findAll(): array
    {
        $entityClass = static::getEntityClass();
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if ( $entityClass instanceof AbstractEntity ) {
            //
        }

        $queryName = 'repository:all:' . $entityClass;

        if ( DoctrineEntityManager::$cacheEnabled === false ) {
            $result = parent::findAll();

            DoctrineEntityManager::addDBRequest( $queryName );

            foreach ( $result as $item ) {
                $item->cacheEnabled = false;
            }

            return $result;
        }

        $key = sprintf( '%s:cache:%s:', SERVER_NAME, $queryName );

        if ( ( $cache = rc()->get( $key ) ) === null ) {
            $arr = parent::findAll();

            DoctrineEntityManager::addDBRequest( $queryName );

            $entityClass::setForceSerialise( true );

            $cache = j_encode( $arr );

            $entityClass::setForceSerialise( false );

            rc()->set( $key, $cache );

            return $arr;
        }

        $arr = [];
        foreach ( j_decode( $cache ) as $entity ) {
            $arr[] = $entityClass::createFromParams( $entity );
        }

        return $arr;
    }

    final public function findOneBy( array $criteria, ?array $orderBy = null ): AbstractEntity|null
    {
        $entityClass = static::getEntityClass();
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if ( $entityClass instanceof AbstractEntity ) {
            //
        }

        $queryName = sprintf(
            'repository:oneBy:%s:criteria:%s:orderBy:%s',
            $entityClass::getClassName(),
            j_encode( $criteria ),
            j_encode( $orderBy )
        );

        if ( DoctrineEntityManager::$cacheEnabled === false ) {
            $result = parent::findOneBy( $criteria, $orderBy );

            DoctrineEntityManager::addDBRequest( $queryName );

            return $result;
        }

        $key = sprintf( '%s:cache:%s', SERVER_NAME, $queryName );

        if ( ( $cache = rc()->get( $key ) ) === null ) {
            $entity = parent::findOneBy( $criteria, $orderBy );

            DoctrineEntityManager::addDBRequest( $queryName );

            $entityClass::setForceSerialise( true );

            $cache = j_encode( $entity );

            $entityClass::setForceSerialise( false );

            rc()->set( $key, $cache );

            return $entity;
        }

        return $entityClass::createFromParams( j_decode( $cache ) );
    }

    /**
     * @param array      $criteria
     * @param array|null $orderBy
     * @param null       $limit
     * @param null       $offset
     *
     * @return array<AbstractEntity>
     */
    final public function findBy( array $criteria = [], ?array $orderBy = null, $limit = null, $offset = null ): array
    {
        $entityClass = static::getEntityClass();
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if ( $entityClass instanceof AbstractEntity ) {
            //
        }

        $queryName = sprintf(
            'repository:allBy:%s:criteria:%s:orderBy:%s:limit:%s:offset:%s',
            $entityClass::getClassName(),
            j_encode( $criteria ),
            j_encode( $orderBy ),
            j_encode( $offset ),
            j_encode( $limit )
        );

        if ( DoctrineEntityManager::$cacheEnabled === false ) {
            $result = parent::findBy( $criteria, $orderBy, $limit, $offset );

            DoctrineEntityManager::addDBRequest( $queryName );

            return $result;
        }

        $key = sprintf( '%s:cache:%s', SERVER_NAME, $queryName );

        if ( ( $cache = rc()->get( $key ) ) === null ) {
            $arr = parent::findBy( $criteria, $orderBy, $limit, $offset );

            DoctrineEntityManager::addDBRequest( $queryName );

            $entityClass::setForceSerialise( true );

            $cache = j_encode( $arr );

            $entityClass::setForceSerialise( false );

            rc()->set( $key, $cache );

            return $arr;
        }

        $arr = [];
        foreach ( j_decode( $cache ) as $entity )
            $arr[] = $entityClass::createFromParams( $entity );

        return $arr;
    }


    public function add( AbstractEntity $entity, bool $flush = true ): void
    {
        em()->persist( $entity );

        if ( $flush )
            em()->flush();

    }

    public function remove( AbstractEntity $entity, bool $flush = true ): void
    {
        em()->remove( $entity );

        if ( $flush )
            em()->flush();

    }
}
