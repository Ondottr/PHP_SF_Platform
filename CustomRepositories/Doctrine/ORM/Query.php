<?php declare( strict_types=1 );

namespace Doctrine\ORM;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Internal\Hydration\IterableResult;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\AST\DeleteStatement;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\AST\UpdateStatement;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\Query\ParameterTypeInferer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\ParserResult;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Utility\HierarchyDiscriminatorResolver;
use JetBrains\PhpStorm\ExpectedValues;
use function array_keys;
use function array_values;
use function count;
use function in_array;
use function ksort;
use function sha1;
use function stripos;

/**
 * A Query object represents a DQL query.
 */
final class Query extends AbstractQuery
{
    /**
     * A query object is in CLEAN state when it has NO unparsed/unprocessed DQL parts.
     */
    public const STATE_CLEAN = 1;

    /**
     * A query object is in state DIRTY when it has DQL parts that have not yet been
     * parsed/processed. This is automatically defined as DIRTY when addDqlQueryPart
     * is called.
     */
    public const STATE_DIRTY = 2;

    /* Query HINTS */

    /**
     * The refresh hint turns any query into a refresh query with the result that
     * any local changes in entities are overridden with the fetched values.
     */
    public const HINT_REFRESH = 'doctrine.refresh';

    /**
     * Internal hint: is set to the proxy entity that is currently triggered for loading
     */
    public const HINT_REFRESH_ENTITY = 'doctrine.refresh.entity';

    /**
     * The forcePartialLoad query hint forces a particular query to return
     * partial objects.
     *
     * @todo Rename: HINT_OPTIMIZE
     */
    public const HINT_FORCE_PARTIAL_LOAD = 'doctrine.forcePartialLoad';

    /**
     * The includeMetaColumns query hint causes meta columns like foreign keys and
     * discriminator columns to be selected and returned as part of the query result.
     *
     * This hint does only apply to non-object queries.
     */
    public const HINT_INCLUDE_META_COLUMNS = 'doctrine.includeMetaColumns';

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

    public const HINT_INTERNAL_ITERATION = 'doctrine.internal.iteration';

    public const HINT_LOCK_MODE = 'doctrine.lockMode';

    /**
     * The current state of this query.
     *
     * @var int
     * @psalm-var self::STATE_*
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
     */
    private ParserResult|null $parserResult;

    /**
     * The first result to return (the "offset").
     *
     * @var int
     */
    private int $firstResult = 0;

    /**
     * The maximum number of results to return (the "limit").
     *
     * @var int|null
     */
    private ?int $maxResults = null;

    /**
     * Gets the SQL query/queries that correspond to this DQL query.
     *
     * @return list<string>|string The built sql query or an array of all sql queries.
     */
    public function getSQL(): string|array
    {
        return $this->parse()->getSqlExecutor()->getSqlStatements();
    }

    /**
     * Returns the corresponding AST for this DQL query.
     */
    public function getAST(): SelectStatement|UpdateStatement|DeleteStatement
    {
        return ( new Parser( $this ) )->getAST();
    }

    /**
     * {@inheritdoc}
     */
    protected function getResultSetMapping(): ResultSetMapping
    {
        // parse query or load from cache
        if ( $this->_resultSetMapping === null )
            $this->_resultSetMapping = $this->parse()->getResultSetMapping();

        return $this->_resultSetMapping;
    }

    /**
     * Parses the DQL query, if necessary, and stores the parser result.
     *
     * Note: Populates $this->_parserResult as a side-effect.
     */
    private function parse(): ParserResult
    {
        $types = [];

        foreach ( $this->parameters as $parameter )
            /** @var Query\Parameter $parameter */
            $types[ $parameter->getName() ] = $parameter->getType();


        // Return previous parser result if the query and the filter collection are both clean
        if ( $this->_state === self::STATE_CLEAN && $this->parsedTypes === $types && $this->_em->isFiltersStateClean() )
            return $this->parserResult;

        $this->_state = self::STATE_CLEAN;
        $this->parsedTypes = $types;

        return $this->parserResult = ( new Parser( $this ) )->parse();
    }

    /**
     * {@inheritdoc}
     */
    protected function _doExecute(): Result|int
    {
        $executor = $this->parse()->getSqlExecutor();

        $executor->removeQueryCacheProfile();

        if ( $this->_resultSetMapping === null )
            $this->_resultSetMapping = $this->parserResult->getResultSetMapping();

        // Prepare parameters
        $paramMappings = $this->parserResult->getParameterMappings();
        $paramCount = count( $this->parameters );
        $mappingCount = count( $paramMappings );

        if ( $paramCount > $mappingCount )
            throw QueryException::tooManyParameters( $mappingCount, $paramCount );

        if ( $paramCount < $mappingCount )
            throw QueryException::tooFewParameters( $mappingCount, $paramCount );

        [ $sqlParams, $types ] = $this->processParameterMappings( $paramMappings );

        return $executor->execute( $this->_em->getConnection(), $sqlParams, $types );
    }

    /**
     * Processes query parameter mappings.
     *
     * @param array<list<int>> $paramMappings
     *
     * @return mixed[][]
     * @psalm-return array{0: list<mixed>, 1: array}
     *
     * @throws Query\QueryException
     */
    private function processParameterMappings( array $paramMappings ): array
    {
        $sqlParams = [];
        $types = [];

        foreach ( $this->parameters as $parameter ) {
            $key = $parameter->getName();

            if ( isset( $paramMappings[ $key ] ) === false )
                throw QueryException::unknownParameter( $key );

            [ $value, $type ] = $this->resolveParameterValue( $parameter );

            foreach ( $paramMappings[ $key ] as $position )
                $types[ $position ] = $type;

            $sqlPositions = $paramMappings[ $key ];

            // optimized multi value sql positions away for now,
            // they are not allowed in DQL anyways.
            $value = [ $value ];
            $countValue = count( $value );

            foreach ( $sqlPositions as $i => $iValue )
                $sqlParams[ $iValue ] = $value[ $i % $countValue ];

        }

        if ( count( $sqlParams ) !== count( $types ) )
            throw QueryException::parameterTypeMismatch();

        if ( $sqlParams ) {
            ksort( $sqlParams );
            $sqlParams = array_values( $sqlParams );

            ksort( $types );
            $types = array_values( $types );
        }

        return [ $sqlParams, $types ];
    }

    /**
     * @return mixed[] tuple of (value, type)
     * @psalm-return array{0: mixed, 1: mixed}
     */
    private function resolveParameterValue( Parameter $parameter ): array
    {
        if ( $parameter->typeWasSpecified() )
            return [ $parameter->getValue(), $parameter->getType() ];

        $key = $parameter->getName();
        $originalValue = $parameter->getValue();
        $value = $originalValue;
        $rsm = $this->getResultSetMapping();

        if ( $value instanceof ClassMetadata && isset( $rsm->metadataParameterMapping[ $key ] ) )
            /** @noinspection NullPointerExceptionInspection */
            $value = $value->getMetadataValue( $rsm->metadataParameterMapping[ $key ] );

        if ( $value instanceof ClassMetadata && isset( $rsm->discriminatorParameters[ $key ] ) )
            $value = array_keys( HierarchyDiscriminatorResolver::resolveDiscriminatorsForClass( $value, $this->_em ) );

        $processedValue = $this->processParameterValue( $value );

        return [
            $processedValue,
            $originalValue === $processedValue
                ? $parameter->getType()
                : ParameterTypeInferer::inferType( $processedValue ),
        ];
    }

    public function free(): void
    {
        parent::free();

        $this->dql = null;
        $this->_state = self::STATE_CLEAN;
    }

    /**
     * Sets a DQL query string.
     *
     * @param string $dqlQuery DQL Query.
     */
    public function setDQL( string $dqlQuery ): self
    {
        $this->dql = $dqlQuery;
        $this->_state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Returns the DQL query that is represented by this query object.
     */
    public function getDQL(): string|null
    {
        return $this->dql;
    }

    /**
     * Returns the state of this query object
     * By default the type is Doctrine_ORM_Query_Abstract::STATE_CLEAN but if it appears any unprocessed DQL
     * part, it is switched to Doctrine_ORM_Query_Abstract::STATE_DIRTY.
     *
     * @return int The query state.
     * @psalm-return self::STATE_* The query state.
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
     * @param string $dql Arbitrary piece of DQL to check for.
     */
    public function contains( string $dql ): bool
    {
        return stripos( $this->getDQL(), $dql ) !== false;
    }

    /**
     * Sets the position of the first result to retrieve (the "offset").
     *
     * @param int|null $firstResult The first result to return.
     *
     * @return $this
     */
    public function setFirstResult( int $firstResult ): self
    {
        $this->firstResult = $firstResult;
        $this->_state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Gets the position of the first result the query object was set to retrieve (the "offset").
     * Returns NULL if {@link setFirstResult} was not applied to this query.
     *
     * @return int|null The position of the first result.
     */
    public function getFirstResult(): int|null
    {
        return $this->firstResult;
    }

    /**
     * Sets the maximum number of results to retrieve (the "limit").
     *
     * @param int|null $maxResults
     *
     * @return $this
     */
    public function setMaxResults( int|null $maxResults ): self
    {
        $this->maxResults = $maxResults;
        $this->_state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Gets the maximum number of results the query object was set to retrieve (the "limit").
     * Returns NULL if {@link setMaxResults} was not applied to this query.
     *
     * @return int|null Maximum number of results.
     */
    public function getMaxResults(): int|null
    {
        return $this->maxResults;
    }

    /**
     * Executes the query and returns an IterableResult that can be used to incrementally
     * iterated over the result.
     *
     * @param ArrayCollection|mixed[]|null $parameters The query parameters.
     * @param string|int $hydrationMode The hydration mode to use.
     * @psalm-param ArrayCollection<int, Parameter>|array<string, mixed>|null $parameters
     * @psalm-param string|AbstractQuery::HYDRATE_*|null $hydrationMode
     * @deprecated
     *
     */
    public function iterate( $parameters = null, $hydrationMode = self::HYDRATE_OBJECT ): IterableResult
    {
        $this->setHint( self::HINT_INTERNAL_ITERATION, true );

        return parent::iterate( $parameters, $hydrationMode );
    }

    /** {@inheritDoc} */
    public function toIterable( iterable $parameters = [], $hydrationMode = self::HYDRATE_OBJECT ): iterable
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

    /**
     * {@inheritdoc}
     */
    public function setHydrationMode( $hydrationMode ): self
    {
        $this->_state = self::STATE_DIRTY;

        return parent::setHydrationMode( $hydrationMode );
    }

    /**
     * Set the lock mode for this Query.
     *
     * @return $this
     *
     * @throws TransactionRequiredException
     * @see LockMode
     *
     */
    public function setLockMode( #[ExpectedValues( valuesFromClass: LockMode::class )] int $lockMode ): self
    {
        if ( in_array( $lockMode, [ LockMode::NONE, LockMode::PESSIMISTIC_READ, LockMode::PESSIMISTIC_WRITE ], true ) )
            if ( !$this->_em->getConnection()->isTransactionActive() )
                throw TransactionRequiredException::transactionRequired();

        $this->setHint( self::HINT_LOCK_MODE, $lockMode );

        return $this;
    }

    /**
     * Get the current lock mode for this query.
     *
     * @return int|null The current lock mode of this query or NULL if no specific lock mode is set.
     */
    public function getLockMode(): int|null
    {
        $lockMode = $this->getHint( self::HINT_LOCK_MODE );

        if ( $lockMode === false ) {
            return null;
        }

        return $lockMode;
    }

    protected function getHash(): string
    {
        return sha1( parent::getHash() . '-' . $this->firstResult . '-' . $this->maxResults );
    }

    /**
     * Cleanup Query resource when clone is called.
     */
    public function __clone()
    {
        parent::__clone();

        $this->_state = self::STATE_DIRTY;
    }
}
