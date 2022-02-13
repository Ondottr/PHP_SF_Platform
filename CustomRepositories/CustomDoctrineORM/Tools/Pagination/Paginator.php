<?php declare( strict_types=1 );

namespace Doctrine\ORM\Tools\Pagination;

use Countable;
use ArrayIterator;
use IteratorAggregate;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Parameter;
use PHP_SF\System\Database\Query;
use PHP_SF\System\Database\Parser;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use function count;
use function array_map;
use function array_sum;
use function array_key_exists;

/**
 * The paginator can handle various complex scenarios with DQL.
 */
class Paginator implements Countable, IteratorAggregate
{

    private QueryBuilder|Query $query;

    private bool $fetchJoinCollection;

    private ?bool $useOutputWalkers;

    private int $count;

    /**
     * @param Query|QueryBuilder $query               A Doctrine ORM query or query builder.
     * @param bool               $fetchJoinCollection Whether the query joins a collection (true by default).
     */
    public function __construct( Query|QueryBuilder $query, bool $fetchJoinCollection = true )
    {
        if ( $query instanceof QueryBuilder )
            $query = $query->getQuery();


        $this->query               = $query;
        $this->fetchJoinCollection = $fetchJoinCollection;
    }

    /**
     * Returns the query.
     *
     * @return Query
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Returns whether the query joins a collection.
     *
     * @return bool Whether the query joins a collection.
     */
    public function getFetchJoinCollection(): bool
    {
        return $this->fetchJoinCollection;
    }

    /**
     * Returns whether the paginator will use an output walker.
     */
    public function getUseOutputWalkers(): ?bool
    {
        return $this->useOutputWalkers;
    }

    /**
     * Sets whether the paginator will use an output walker.
     */
    public function setUseOutputWalkers( ?bool $useOutputWalkers ): self
    {
        $this->useOutputWalkers = $useOutputWalkers;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        if ( !isset( $this->count ) )
            $this->count = (int)array_sum( array_map( 'current', $this->getCountQuery()->getScalarResult() ) );


        return $this->count;
    }

    /**
     * Returns Query prepared to count.
     */
    private function getCountQuery(): Query
    {
        $countQuery = $this->cloneQuery( $this->query );

        if ( !$countQuery->hasHint( CountWalker::HINT_DISTINCT ) ) {
            $countQuery->setHint( CountWalker::HINT_DISTINCT, true );
        }

        if ( $this->useOutputWalker( $countQuery ) ) {
            $platform = $countQuery->getEntityManager()->getConnection()->getDatabasePlatform(); // law of demeter win

            $rsm = new ResultSetMapping();
            $rsm->addScalarResult( $this->getSQLResultCasing( $platform, 'dctrn_count' ), 'count' );

            $countQuery->setHint( Query::HINT_CUSTOM_OUTPUT_WALKER, CountOutputWalker::class );
            $countQuery->setResultSetMapping( $rsm );
        }
        else {
            $this->appendTreeWalker( $countQuery, CountWalker::class );
            $this->unbindUnusedQueryParams( $countQuery );
        }

        $countQuery->setFirstResult( null )->setMaxResults( null );

        return $countQuery;
    }

    private function cloneQuery( Query $query ): Query
    {
        $cloneQuery = clone $query;

        $cloneQuery->setParameters( clone $query->getParameters() );
        $cloneQuery->setCacheable( false );

        foreach ( $query->getHints() as $name => $value ) {
            $cloneQuery->setHint( $name, $value );
        }

        return $cloneQuery;
    }

    /**
     * Determines whether to use an output walker for the query.
     */
    private function useOutputWalker( Query $query ): bool
    {
        if ( $this->useOutputWalkers === null ) {
            return (bool)$query->getHint( Query::HINT_CUSTOM_OUTPUT_WALKER ) === false;
        }

        return $this->useOutputWalkers;
    }

    private function getSQLResultCasing( AbstractPlatform $platform, string $column ): string
    {
        if ( $platform instanceof DB2Platform || $platform instanceof OraclePlatform ) {
            return strtoupper( $column );
        }

        if ( $platform instanceof PostgreSQLPlatform ) {
            return strtolower( $column );
        }

        if ( method_exists( AbstractPlatform::class, 'getSQLResultCasing' ) ) {
            return $platform->getSQLResultCasing( $column );
        }

        return $column;
    }

    /**
     * Appends a custom tree walker to the tree walkers hint.
     *
     * @psalm-param class-string $walkerClass
     */
    private function appendTreeWalker( Query $query, string $walkerClass ): void
    {
        $hints = $query->getHint( Query::HINT_CUSTOM_TREE_WALKERS );

        if ( $hints === false ) {
            $hints = [];
        }

        $hints[] = $walkerClass;
        $query->setHint( Query::HINT_CUSTOM_TREE_WALKERS, $hints );
    }

    private function unbindUnusedQueryParams( Query $query ): void
    {
        $parser            = new Parser( $query );
        $parameterMappings = $parser->parse()->getParameterMappings();
        /** @var Collection|Parameter[] $parameters */
        $parameters = $query->getParameters();

        foreach ( $parameters as $key => $parameter ) {
            $parameterName = $parameter->getName();

            if ( !( isset( $parameterMappings[ $parameterName ] ) ||
                    array_key_exists( $parameterName, $parameterMappings ) ) ) {
                unset( $parameters[ $key ] );
            }
        }

        $query->setParameters( $parameters );
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): ArrayIterator
    {
        $offset = $this->query->getFirstResult();
        $length = $this->query->getMaxResults();

        if ( $this->fetchJoinCollection && $length !== null ) {
            $subQuery = $this->cloneQuery( $this->query );

            if ( $this->useOutputWalker( $subQuery ) )
                $subQuery->setHint( Query::HINT_CUSTOM_OUTPUT_WALKER, LimitSubqueryOutputWalker::class );

            else {
                $this->appendTreeWalker( $subQuery, LimitSubqueryWalker::class );
                $this->unbindUnusedQueryParams( $subQuery );
            }

            $subQuery->setFirstResult( $offset )->setMaxResults( $length );

            $foundIdRows = $subQuery->getScalarResult();

            // don't do this for an empty id array
            if ( $foundIdRows === [] )
                return new ArrayIterator( [] );


            $whereInQuery = $this->cloneQuery( $this->query );
            $ids          = array_map( 'current', $foundIdRows );

            $this->appendTreeWalker( $whereInQuery, WhereInWalker::class );
            $whereInQuery->setHint( WhereInWalker::HINT_PAGINATOR_ID_COUNT, count( $ids ) );
            $whereInQuery->setFirstResult( null )->setMaxResults( null );
            $whereInQuery->setParameter( WhereInWalker::PAGINATOR_ID_ALIAS, $ids );
            $whereInQuery->setCacheable( $this->query->isCacheable() );

            $result = $whereInQuery->getResult( $this->query->getHydrationMode() );
        }
        else {
            $result = $this
                ->cloneQuery( $this->query )
                ->setMaxResults( $length )
                ->setFirstResult( $offset )
                ->setCacheable( $this->query->isCacheable() )
                ->getResult( $this->query->getHydrationMode() );
        }

        return new ArrayIterator( $result );
    }
}
