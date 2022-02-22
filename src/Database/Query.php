<?php

/** @noinspection PhpMissingParentCallCommonInspection */
declare( strict_types=1 );

namespace PHP_SF\System\Database;

use Doctrine\DBAL\Result;
use JetBrains\PhpStorm\Pure;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\Query\ParserResult;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\AST\DeleteStatement;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\AST\UpdateStatement;
use Doctrine\ORM\Query\ParameterTypeInferer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Exec\AbstractSqlExecutor;
use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use Doctrine\ORM\Utility\HierarchyDiscriminatorResolver;
use function count;
use function assert;
use function is_int;
use function is_array;


final class Query extends AbstractQuery
{

    /* Caching */

    public const CACHE_TYPE_PERSISTENT = 0;
    public const CACHE_TYPE_TEMPORARY  = 1;
    public const CACHE_TYPE_DISABLED   = 2;
    /**
     * A query object is in CLEAN state when it has NO unparsed/unprocessed DQL parts.
     */
    public const STATE_CLEAN = 1;
    /**
     * A query object is in state DIRTY when it has DQL parts that have not yet been
     * parsed/processed. This is automatically defined as DIRTY when addDqlQueryPart
     * is called.
     */
    public const STATE_DIRTY      = 2;
    public const HINT_CACHE_EVICT = 'doctrine.cache.evict';

    /* Query HINTS */
    /**
     * An array of class names that implement \Doctrine\ORM\Query\TreeWalker and
     * are iterated and executed after the DQL has been parsed into an AST.
     */
    public const HINT_CUSTOM_TREE_WALKERS = 'doctrine.customTreeWalkers';
    /**
     * A string with a class name that implements \Doctrine\ORM\Query\TreeWalker
     * and is used for generating the target SQL from any DQL AST tree.
     */
    public const HINT_CUSTOM_OUTPUT_WALKER = 'doctrine.customOutputWalker';
    public const HINT_INTERNAL_ITERATION   = 'doctrine.internal.iteration';
    private static int $currentCacheType = self::CACHE_TYPE_PERSISTENT;
    /**
     * The current state of this query.
     *
     * @var int
     */
    private int $_state = self::STATE_DIRTY;

    /**
     * A snapshot of the parameter types the query was parsed with.
     *
     * @var array<string,Type>
     */
    private array $parsedTypes = [];

    /**
     * Cached DQL query.
     *
     * @var string|null
     */
    private ?string $dql = null;

    /**
     * The parser result that holds DQL => SQL information.
     *
     * @var ParserResult|null
     */
    private ?ParserResult $parserResult;

    /**
     * The first result to return (the "offset").
     *
     * @var int|null
     */
    private ?int $firstResult = null;

    /**
     * The maximum number of results to return (the "limit").
     *
     * @var int|null
     */
    private ?int $maxResults = null;

    /**
     * @override
     */
    public function free(): void
    {
        parent::free();

        $this->dql    = null;
        $this->_state = self::STATE_CLEAN;
    }

    /**
     * Returns the state of this query object
     * By default the type is Doctrine_ORM_Query_Abstract::STATE_CLEAN but if it appears any unprocessed DQL
     * part, it is switched to Doctrine_ORM_Query_Abstract::STATE_DIRTY.
     *
     * @return int The query state.
     * @see AbstractQuery::STATE_DIRTY
     *
     * @see AbstractQuery::STATE_CLEAN
     */
    public function getState(): int
    {
        return $this->_state;
    }

    /**
     * Method to check if an arbitrary piece of DQL exists
     *
     * @param string|null $dql Arbitrary piece of DQL to check for.
     *
     * @return bool
     */
    #[Pure]
    public function contains( ?string $dql ): bool
    {
        return stripos( $this->getDQL(), $dql ) !== false;
    }

    /**
     * Returns the DQL query that is represented by this query object.
     */
    public function getDQL(): ?string
    {
        return $this->dql;
    }

    /**
     * Sets a DQL query string.
     *
     * @param string|null $dqlQuery DQL Query.
     *
     * @return Query
     */
    public function setDQL( ?string $dqlQuery ): self
    {
        if ( $dqlQuery !== null ) {
            $this->dql    = $dqlQuery;
            $this->_state = self::STATE_DIRTY;
        }

        return $this;
    }

    /**
     * Gets the position of the first result the query object was set to retrieve (the "offset").
     * Returns NULL if {@link setFirstResult} was not applied to this query.
     *
     * @return int|null The position of the first result.
     */
    public function getFirstResult(): ?int
    {
        return $this->firstResult;
    }

    /**
     * Sets the position of the first result to retrieve (the "offset").
     *
     * @param int|null $firstResult The first result to return.
     *
     * @return self This query object.
     */
    public function setFirstResult( ?int $firstResult ): self
    {
        $this->firstResult = $firstResult;
        $this->_state      = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Gets the maximum number of results the query object was set to retrieve (the "limit").
     * Returns NULL if {@link setMaxResults} was not applied to this query.
     *
     * @return int|null Maximum number of results.
     */
    public function getMaxResults(): ?int
    {
        return $this->maxResults;
    }

    /**
     * Sets the maximum number of results to retrieve (the "limit").
     *
     * @param int|null $maxResults
     *
     * @return self This query object.
     */
    public function setMaxResults( ?int $maxResults ): self
    {
        $this->maxResults = $maxResults;
        $this->_state     = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Executes the query and returns an IterableResult that can be used to incrementally
     * iterated over the result.
     *
     * @param ArrayCollection|array|null                                      $parameters    The query parameters.
     * @param string|int                                                      $hydrationMode The hydration mode to use.
     *
     * @psalm-param ArrayCollection<int, Parameter>|array<string, mixed>|null $parameters
     * @deprecated
     *
     */
    public function iterate( $parameters = null, $hydrationMode = self::HYDRATE_OBJECT ): iterable
    {
        $this->setHint( self::HINT_INTERNAL_ITERATION, true );

        return parent::toIterable( $parameters, $hydrationMode );
    }

    /**
     * {@inheritdoc}
     */
    public function setHint( $name, $value ): self
    {
        $this->_state = self::STATE_DIRTY;

        return parent::setHint( $name, $value );
    }

    /** {@inheritDoc} */
    public function toIterable( iterable $parameters = [], $hydrationMode = self::HYDRATE_OBJECT ): iterable
    {
        $this->setHint( self::HINT_INTERNAL_ITERATION, true );

        return parent::toIterable( $parameters, $hydrationMode );
    }

    /**
     * Cleanup Query resource when clone is called.
     */
    public function __clone(): void
    {
        parent::__clone();

        $this->_state = self::STATE_DIRTY;
    }

    /**
     * @param int $hydrationMode
     *
     * @return mixed
     */
    public function getResult( $hydrationMode = self::HYDRATE_OBJECT ): mixed
    {
        if ( self::getCurrentCacheType() === self::CACHE_TYPE_DISABLED ) {
            $result = parent::getResult( $hydrationMode );

            DoctrineEntityManager::addDBRequest( $this->getSQL() );

            return $result;
        }

        $ifSelectQuery = str_contains( $this->getDQL(), 'SELECT' ) &&
                         str_contains( $this->getDQL(), 'FROM' );
        if ( $ifSelectQuery === true ) {
            $queryRedisKey = sprintf( '%s:cache:queryBuilder:%s', SERVER_NAME, $this->getDQL() );

            foreach ( $this->getParameters() as $parameter ) {
                $queryRedisKey = str_replace(
                    ":{$parameter->getName()}",
                    j_encode( $parameter->getValue() ),
                    $queryRedisKey
                );
            }

            if ( ( $cachedResult = rc()->get( $queryRedisKey ) ) !== null ) {
                $cachedResult = json_decode( $cachedResult, false, 512, JSON_THROW_ON_ERROR );

                foreach ( AbstractEntity::getEntitiesList() as $entityName )
                    if ( str_contains( $this->getDQL(), "$entityName " ) )
                        break;


                if ( isset( $entityName ) ) {
                    /** @noinspection PhpStatementHasEmptyBodyInspection */
                    if ( $entityName instanceof AbstractEntity ) {
                    }

                    if ( is_array( $cachedResult ) ) {
                        $entities = [];
                        foreach ( $cachedResult as $item ) {
                            if ( is_int( $item ) ) {
                                $item = em()->getRepository( $entityName )->find( $item );
                                rp()->del( $queryRedisKey );
                            }

                            $entities[] = $entityName::createFromParams( $item );
                        }

                        return $entities;
                    }

                    return $cachedResult;
                }
            }
        }

        $queryResult = parent::getResult( $hydrationMode );

        DoctrineEntityManager::addDBRequest( $this->getSQL() );

        if ( $ifSelectQuery ) {
            if ( self::getCurrentCacheType() === self::CACHE_TYPE_PERSISTENT ) {
                rp()->set( $queryRedisKey, json_encode( $queryResult, JSON_THROW_ON_ERROR ) );
            }
            elseif ( self::getCurrentCacheType() === self::CACHE_TYPE_TEMPORARY ) {
                rp()->setex(
                    $queryRedisKey,
                    DOCTRINE_QUERY_BUILDER_CACHE_TIME,
                    json_encode( $queryResult, JSON_THROW_ON_ERROR )
                );
            }
        }
        elseif ( ( str_contains( $this->getDQL(), 'INSERT' ) && str_contains( $this->getDQL(), 'INTO' ) ) ||
                 ( ( str_contains( $this->getDQL(), 'UPDATE' ) && str_contains( $this->getDQL(), 'SET' ) ) )
        ) {
            $this->clearCacheForCurrentQuery();
        }

        return $queryResult;
    }

    public static function getCurrentCacheType(): int
    {
        return self::$currentCacheType;
    }

    public static function setCurrentCacheType( int $currentCacheType ): void
    {
        self::$currentCacheType = $currentCacheType;
    }

    /**
     * Gets the SQL query/queries that correspond to this DQL query.
     *
     * @return string|array The built sql query or an array of all sql queries.
     *
     * @override
     */
    public function getSQL(): string|array
    {
        return $this->parse()->getSqlExecutor()->getSqlStatements();
    }

    /**
     * Parses the DQL query, if necessary, and stores the parser result.
     *
     * Note: Populates $this->_parserResult as a side-effect.
     */
    private function parse(): ParserResult
    {
        $types = [];

        foreach ( $this->parameters as $parameter ) {
            assert( $parameter instanceof Parameter );
            $types[ $parameter->getName() ] = $parameter->getType();
        }

        // Return previous parser result if the query and the filter collection are both clean
        if ( $this->_state === self::STATE_CLEAN &&
             $this->parsedTypes === $types &&
             $this->_em->isFiltersStateClean() ) {
            return $this->parserResult;
        }

        $this->_state      = self::STATE_CLEAN;
        $this->parsedTypes = $types;

        // Cache miss.
        $parser = new Parser( $this );

        $this->parserResult = $parser->parse();

        return $this->parserResult;
    }

    private function clearCacheForCurrentQuery(): void
    {
        foreach ( DoctrineEntityManager::getEntityDirectories() as $entityDirectory ) {
            $entities = array_diff( scandir( $entityDirectory ), [ '.', '..' ] );

            foreach ( $entities as $entityName ) {
                $file = file_get_contents( $entityDirectory . '/' . $entityName );

                $namespace = explode( ';', explode( 'namespace ', $file )[1] )[0];

                $className = $namespace . '\\' . explode( '.php', $entityName )[0];

                if ( str_contains( $this->getDQL(), $className ) ) {
                    /** @noinspection PhpUndefinedMethodInspection */
                    $className::clearRepositoryCache();
                    /** @noinspection PhpUndefinedMethodInspection */
                    $className::clearQueryBuilderCache();
                }
            }
        }
    }

    public function execute( $parameters = null, $hydrationMode = null ): mixed
    {
        $data = $this->executeIgnoreQueryCache( $parameters, $hydrationMode );

        if ( ( str_contains( $this->getDQL(), 'INSERT' ) && str_contains( $this->getDQL(), 'INTO' ) ) ||
             ( ( str_contains( $this->getDQL(), 'UPDATE' ) && str_contains( $this->getDQL(), 'SET' ) ) )
        ) {
            $this->clearCacheForCurrentQuery();
        }

        return $data;
    }

    private function executeIgnoreQueryCache( $parameters = null, $hydrationMode = null )
    {
        if ( $hydrationMode !== null ) {
            $this->setHydrationMode( $hydrationMode );
        }

        if ( !empty( $parameters ) ) {
            $this->setParameters( $parameters );
        }

        $stmt = $this->_doExecute();

        if ( is_numeric( $stmt ) ) {
            return $stmt;
        }

        $rsm = $this->getResultSetMapping();

        return $this->_em->newHydrator( $this->_hydrationMode )->hydrateAll( $stmt, $rsm, $this->_hints );
    }

    /**
     * {@inheritdoc}
     */
    public function setHydrationMode( $hydrationMode ): self
    {
        $this->_state = self::STATE_DIRTY;

        return parent::setHydrationMode( $hydrationMode );
    }

    /**
     * Executes the query and returns a resulting Statement object.
     *
     * @return Result|int The executed database statement that holds
     *                             the results, or an integer indicating how
     *                             many rows were affected.
     *
     * @noinspection DuplicatedCode
     */
    protected function _doExecute(): Result|int
    {
        $executor = $this->parse()->getSqlExecutor();

        if ( $this->_queryCacheProfile ) {
            $executor->setQueryCacheProfile( $this->_queryCacheProfile );
        }
        else {
            $executor->removeQueryCacheProfile();
        }

        if ( $this->_resultSetMapping === null ) {
            $this->_resultSetMapping = $this->parserResult->getResultSetMapping();
        }

        // Prepare parameters
        $paramMappings = $this->parserResult->getParameterMappings();
        $paramCount    = count( $this->parameters );
        $mappingCount  = count( $paramMappings );

        if ( $paramCount > $mappingCount ) {
            throw QueryException::tooManyParameters( $mappingCount, $paramCount );
        }

        if ( $paramCount < $mappingCount ) {
            throw QueryException::tooFewParameters( $mappingCount, $paramCount );
        }

        // evict all cache for the entity region
        if ( $this->hasCache &&
             isset( $this->_hints[ self::HINT_CACHE_EVICT ] ) &&
             $this->_hints[ self::HINT_CACHE_EVICT ] ) {
            $this->evictEntityCacheRegion();
        }

        [ $sqlParams, $types ] = $this->processParameterMappings( $paramMappings );

        $this->evictResultSetCache(
            $executor,
            $sqlParams,
            $types,
            $this->_em->getConnection()->getParams()
        );

        return $executor->execute( $this->_em->getConnection(), $sqlParams, $types );
    }

    /**
     * Evict entity cache region
     */
    private function evictEntityCacheRegion(): void
    {
        $AST = $this->getAST();

        if ( $AST instanceof SelectStatement ) {
            throw new QueryException( 'The hint "HINT_CACHE_EVICT" is not valid for select statements.' );
        }

        $className = $AST instanceof DeleteStatement
            ? $AST->deleteClause->abstractSchemaName
            : $AST->updateClause->abstractSchemaName;

        $this->_em->getCache()?->evictEntityRegion( $className );
    }

    /**
     * Returns the corresponding AST for this DQL query.
     *
     * @return SelectStatement|UpdateStatement|DeleteStatement
     */
    public function getAST(): DeleteStatement|UpdateStatement|SelectStatement
    {
        $parser = new Parser( $this );

        return $parser->getAST();
    }

    /**
     * Processes query parameter mappings.
     *
     * @param array<list<int>> $paramMappings
     *
     * @return array[]
     * @psalm-return array{0: list<mixed>, 1: array}
     *
     * @throws QueryException
     *
     * @noinspection DuplicatedCode
     */
    private function processParameterMappings( array $paramMappings ): array
    {
        $sqlParams = [];
        $types     = [];

        foreach ( $this->parameters as $parameter ) {
            $key = $parameter->getName();

            if ( !isset( $paramMappings[ $key ] ) ) {
                throw QueryException::unknownParameter( $key );
            }

            [ $value, $type ] = $this->resolveParameterValue( $parameter );

            foreach ( $paramMappings[ $key ] as $position ) {
                $types[ $position ] = $type;
            }

            $sqlPositions = $paramMappings[ $key ];

            // optimized multi value sql positions away for now,
            // they are not allowed in DQL anyways.
            $value      = [ $value ];
            $countValue = count( $value );

            foreach ( $sqlPositions as $i => $iValue ) {
                $sqlParams[ $iValue ] = $value[ $i % $countValue ];
            }
        }

        if ( count( $sqlParams ) !== count( $types ) ) {
            throw QueryException::parameterTypeMismatch();
        }

        if ( $sqlParams ) {
            ksort( $sqlParams );
            $sqlParams = array_values( $sqlParams );

            ksort( $types );
            $types = array_values( $types );
        }

        return [ $sqlParams, $types ];
    }

    /**
     * @return array tuple of (value, type)
     * @psalm-return array{0: mixed, 1: mixed}
     * @noinspection DuplicatedCode
     */
    private function resolveParameterValue( Parameter $parameter ): array
    {
        if ( $parameter->typeWasSpecified() ) {
            return [ $parameter->getValue(), $parameter->getType() ];
        }

        $key           = $parameter->getName();
        $originalValue = $parameter->getValue();
        $value         = $originalValue;
        $rsm           = $this->getResultSetMapping();

        assert( $rsm !== null );

        if ( $value instanceof ClassMetadata && isset( $rsm->metadataParameterMapping[ $key ] ) ) {
            $value = $value->getMetadataValue( $rsm->metadataParameterMapping[ $key ] );
        }

        if ( $value instanceof ClassMetadata && isset( $rsm->discriminatorParameters[ $key ] ) ) {
            $value = array_keys( HierarchyDiscriminatorResolver::resolveDiscriminatorsForClass( $value, $this->_em ) );
        }

        $processedValue = $this->processParameterValue( $value );

        return [
            $processedValue,
            $originalValue === $processedValue
                ? $parameter->getType()
                : ParameterTypeInferer::inferType( $processedValue ),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getResultSetMapping(): ResultSetMapping
    {
        // parse query or load from cache
        if ( $this->_resultSetMapping === null ) {
            $this->_resultSetMapping = $this->parse()->getResultSetMapping();
        }

        return $this->_resultSetMapping;
    }

    /**
     * @param array<string,mixed> $sqlParams
     * @param array<string,Type>  $types
     * @param array<string,mixed> $connectionParams
     */
    private function evictResultSetCache(
        AbstractSqlExecutor $executor,
        array               $sqlParams,
        array               $types,
        array               $connectionParams
    ): void {
        if ( $this->_queryCacheProfile === null || !$this->getExpireResultCache() ) {
            return;
        }

        $cacheDriver = $this->_queryCacheProfile->getResultCacheDriver();
        $statements  = (array)$executor->getSqlStatements(); // Type casted since it can either be a string or an array

        foreach ( $statements as $statement ) {
            $cacheKeys = $this->_queryCacheProfile->generateCacheKeys(
                $statement,
                $sqlParams,
                $types,
                $connectionParams
            );

            $cacheDriver?->delete( reset( $cacheKeys ) );
        }
    }

    protected function getHash(): string
    {
        return sha1( parent::getHash() . '-' . $this->firstResult . '-' . $this->maxResults );
    }

}
