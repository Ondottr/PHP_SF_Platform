<?php /** @noinspection MethodShouldBeFinalInspection */
declare( strict_types=1 );

namespace Doctrine\ORM;

use BackedEnum;
use Countable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\DBAL\Result;
use Doctrine\ORM\Internal\Hydration\IterableResult;
use Doctrine\ORM\Mapping\MappingException as ORMMappingException;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\Mapping\MappingException;
use LogicException;
use Traversable;
use function array_map;
use function array_shift;
use function count;
use function in_array;
use function is_array;
use function is_numeric;
use function is_object;
use function is_scalar;
use function iterator_count;
use function iterator_to_array;
use function reset;

/**
 * Base contract for ORM queries. Base class for Query and NativeQuery.
 *
 * @link    www.doctrine-project.org
 */
abstract class AbstractQuery
{
    /* Hydration mode constants */

    /**
     * Hydrates an object graph. This is the default behavior.
     */
    public const HYDRATE_OBJECT = 1;

    /**
     * Hydrates an array graph.
     */
    public const HYDRATE_ARRAY = 2;

    /**
     * Hydrates a flat, rectangular result set with scalar values.
     */
    public const HYDRATE_SCALAR = 3;

    /**
     * Hydrates a single scalar value.
     */
    public const HYDRATE_SINGLE_SCALAR = 4;

    /**
     * Very simple object hydrator (optimized for performance).
     */
    public const HYDRATE_SIMPLEOBJECT = 5;

    /**
     * Hydrates scalar column value.
     */
    public const HYDRATE_SCALAR_COLUMN = 6;

    /**
     * The parameter map of this query.
     *
     * @var ArrayCollection|Parameter[]
     * @psalm-var ArrayCollection<int, Parameter>
     */
    protected array|ArrayCollection $parameters;

    /**
     * The user-specified ResultSetMapping to use.
     *
     * @var ResultSetMapping|null
     */
    protected ResultSetMapping|null $_resultSetMapping = null;

    /**
     * The entity manager used by this query object.
     *
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $_em;

    /**
     * The map of query hints.
     *
     * @psalm-var array<string, mixed>
     */
    protected array $_hints = [];

    /**
     * The hydration mode.
     *
     * @var string|int
     * @psalm-var string|AbstractQuery::HYDRATE_*
     */
    protected string|int $_hydrationMode = self::HYDRATE_OBJECT;

    /** @var int */
    protected int $lifetime = 0;

    /**
     * Initializes a new instance of a class derived from <tt>AbstractQuery</tt>.
     */
    public function __construct( EntityManagerInterface $em )
    {
        $this->_em = $em;
        $this->parameters = new ArrayCollection();
        $this->_hints = $em->getConfiguration()->getDefaultQueryHints();
    }

    /**
     * Gets the SQL query that corresponds to this query object.
     * The returned SQL syntax depends on the connection driver that is used
     * by this query object at the time of this method call.
     *
     * @return list<string>|string SQL query
     */
    abstract public function getSQL(): array|string;

    /**
     * Retrieves the associated EntityManager of this Query instance.
     *
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->_em;
    }

    /**
     * Frees the resources used by the query object.
     *
     * Resets Parameters, Parameter Types and Query Hints.
     *
     * @return void
     */
    public function free(): void
    {
        $this->parameters = new ArrayCollection();

        $this->_hints = $this->_em->getConfiguration()->getDefaultQueryHints();
    }

    /**
     * Get all defined parameters.
     *
     * @return ArrayCollection The defined query parameters.
     * @psalm-return ArrayCollection<int, Parameter>
     */
    public function getParameters(): ArrayCollection|array
    {
        return $this->parameters;
    }

    /**
     * Gets a query parameter.
     *
     * @param int|string $key The key (index or name) of the bound parameter.
     *
     * @return Parameter|null The value of the bound parameter, or NULL if not available.
     */
    public function getParameter( $key ): Parameter|null
    {
        $key = Query\Parameter::normalizeName( $key );

        $filteredParameters = $this->parameters->filter(
            static function ( Query\Parameter $parameter ) use ( $key ): bool {
                $parameterName = $parameter->getName();

                return $key === $parameterName;
            }
        );

        if ( !$filteredParameters->isEmpty() )
            return $filteredParameters->first();

        return null;
    }

    /**
     * Sets a collection of query parameters.
     *
     * @param ArrayCollection|mixed[] $parameters
     * @psalm-param ArrayCollection<int, Parameter>|mixed[] $parameters
     *
     * @return $this
     */
    public function setParameters( $parameters ): static
    {
        if ( is_array( $parameters ) ) {
            /** @psalm-var ArrayCollection<int, Parameter> $parameterCollection */
            $parameterCollection = new ArrayCollection();

            foreach ( $parameters as $key => $value ) {
                $parameterCollection->add( new Parameter( $key, $value ) );
            }

            $parameters = $parameterCollection;
        }

        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Sets a query parameter.
     *
     * @param int|string $key The parameter position or name.
     * @param mixed $value The parameter value.
     * @param int|string|null $type The parameter type. If specified, the given value will be run through
     *                               the type conversion of this type. This is usually not needed for
     *                               strings and numeric types.
     *
     * @return $this
     */
    public function setParameter( int|string $key, mixed $value, int|string $type = null ): static
    {
        $existingParameter = $this->getParameter( $key );

        if ( $existingParameter !== null ) {
            $existingParameter->setValue( $value, $type );

            return $this;
        }

        $this->parameters->add( new Parameter( $key, $value, $type ) );

        return $this;
    }

    /**
     * Processes an individual parameter value.
     *
     * @param mixed $value
     *
     * @return mixed
     *
     * @throws ORMInvalidArgumentException
     */
    public function processParameterValue( $value ): mixed
    {
        if ( is_scalar( $value ) ) {
            return $value;
        }

        if ( $value instanceof Collection ) {
            $value = iterator_to_array( $value );
        }

        if ( is_array( $value ) ) {
            $value = $this->processArrayParameterValue( $value );

            return $value;
        }

        if ( $value instanceof Mapping\ClassMetadata ) {
            return $value->name;
        }

        if ( $value instanceof BackedEnum ) {
            return $value->value;
        }

        if ( !is_object( $value ) ) {
            return $value;
        }

        try {
            $class = ClassUtils::getClass( $value );
            $value = $this->_em->getUnitOfWork()->getSingleIdentifierValue( $value );

            if ( $value === null ) {
                throw ORMInvalidArgumentException::invalidIdentifierBindingEntity( $class );
            }
        } catch ( MappingException|ORMMappingException $e ) {
            /* Silence any mapping exceptions. These can occur if the object in
               question is not a mapped entity, in which case we just don't do
               any preparation on the value.
               Depending on MappingDriver, either MappingException or
               ORMMappingException is thrown. */

            $value = $this->potentiallyProcessIterable( $value );
        }

        return $value;
    }

    /**
     * If no mapping is detected, trying to resolve the value as a Traversable
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function potentiallyProcessIterable( $value ): mixed
    {
        if ( $value instanceof Traversable ) {
            $value = iterator_to_array( $value );
            $value = $this->processArrayParameterValue( $value );
        }

        return $value;
    }

    /**
     * Process a parameter value which was previously identified as an array
     *
     * @param mixed[] $value
     *
     * @return mixed[]
     */
    private function processArrayParameterValue( array $value ): array
    {
        foreach ( $value as $key => $paramValue ) {
            $paramValue = $this->processParameterValue( $paramValue );
            $value[ $key ] = is_array( $paramValue ) ? reset( $paramValue ) : $paramValue;
        }

        return $value;
    }

    /**
     * Sets the ResultSetMapping that should be used for hydration.
     *
     * @return $this
     */
    public function setResultSetMapping( Query\ResultSetMapping $rsm ): static
    {
        $this->translateNamespaces( $rsm );
        $this->_resultSetMapping = $rsm;

        return $this;
    }

    /**
     * Gets the ResultSetMapping used for hydration.
     *
     * @return ResultSetMapping|null
     */
    protected function getResultSetMapping(): ResultSetMapping|null
    {
        return $this->_resultSetMapping;
    }

    /**
     * Allows to translate entity namespaces to full qualified names.
     */
    private function translateNamespaces( Query\ResultSetMapping $rsm ): void
    {
        $translate = function ( $alias ): string {
            return $this->_em->getClassMetadata( $alias )->getName();
        };

        $rsm->aliasMap = array_map( $translate, $rsm->aliasMap );
        $rsm->declaringClasses = array_map( $translate, $rsm->declaringClasses );
    }


    /**
     * Change the default fetch mode of an association for this query.
     *
     * @param class-string $class
     * @param string $assocName
     * @param int $fetchMode
     * @psalm-param Mapping\ClassMetadata::FETCH_EAGER|Mapping\ClassMetadata::FETCH_LAZY $fetchMode
     *
     * @return $this
     */
    public function setFetchMode( $class, $assocName, $fetchMode ): static
    {
        if ( !in_array( $fetchMode, [ Mapping\ClassMetadataInfo::FETCH_EAGER, Mapping\ClassMetadataInfo::FETCH_LAZY ], true ) ) {
            $fetchMode = Mapping\ClassMetadataInfo::FETCH_LAZY;
        }

        $this->_hints['fetchMode'][ $class ][ $assocName ] = $fetchMode;

        return $this;
    }

    /**
     * Defines the processing mode to be used during hydration / result set transformation.
     *
     * @param string|int $hydrationMode Doctrine processing mode to be used during hydration process.
     *                                  One of the Query::HYDRATE_* constants.
     * @psalm-param string|AbstractQuery::HYDRATE_* $hydrationMode
     *
     * @return $this
     */
    public function setHydrationMode( $hydrationMode ): static
    {
        $this->_hydrationMode = $hydrationMode;

        return $this;
    }

    /**
     * Gets the hydration mode currently used by the query.
     *
     * @return string|int
     * @psalm-return string|AbstractQuery::HYDRATE_*
     */
    public function getHydrationMode()
    {
        return $this->_hydrationMode;
    }

    /**
     * Gets the list of results for the query.
     *
     * Alias for execute(null, $hydrationMode = HYDRATE_OBJECT).
     *
     * @param string|int $hydrationMode
     * @psalm-param string|AbstractQuery::HYDRATE_* $hydrationMode
     *
     * @return mixed
     */
    public function getResult( $hydrationMode = self::HYDRATE_OBJECT ): mixed
    {
        return $this->execute( null, $hydrationMode );
    }

    /**
     * Gets the array of results for the query.
     *
     * Alias for execute(null, HYDRATE_ARRAY).
     *
     * @return mixed[]
     */
    public function getArrayResult(): array
    {
        return $this->execute( null, self::HYDRATE_ARRAY );
    }

    /**
     * Gets one-dimensional array of results for the query.
     *
     * Alias for execute(null, HYDRATE_SCALAR_COLUMN).
     *
     * @return mixed[]
     */
    public function getSingleColumnResult(): array
    {
        return $this->execute( null, self::HYDRATE_SCALAR_COLUMN );
    }

    /**
     * Gets the scalar results for the query.
     *
     * Alias for execute(null, HYDRATE_SCALAR).
     *
     * @return mixed[]
     */
    public function getScalarResult(): array
    {
        return $this->execute( null, self::HYDRATE_SCALAR );
    }

    /**
     * Get exactly one result or null.
     *
     * @param string|int|null $hydrationMode
     * @psalm-param string|AbstractQuery::HYDRATE_*|null $hydrationMode
     *
     * @return mixed
     *
     * @throws NonUniqueResultException
     */
    public function getOneOrNullResult( $hydrationMode = null ): mixed
    {
        try {
            $result = $this->execute( null, $hydrationMode );
        } catch ( NoResultException $e ) {
            return null;
        }

        if ( $this->_hydrationMode !== self::HYDRATE_SINGLE_SCALAR && !$result ) {
            return null;
        }

        if ( !is_array( $result ) ) {
            return $result;
        }

        if ( count( $result ) > 1 ) {
            throw new NonUniqueResultException();
        }

        return array_shift( $result );
    }

    /**
     * Gets the single result of the query.
     *
     * Enforces the presence as well as the uniqueness of the result.
     *
     * If the result is not unique, a NonUniqueResultException is thrown.
     * If there is no result, a NoResultException is thrown.
     *
     * @param string|int|null $hydrationMode
     * @psalm-param string|AbstractQuery::HYDRATE_*|null $hydrationMode
     *
     * @return mixed
     *
     * @throws NonUniqueResultException If the query result is not unique.
     * @throws NoResultException        If the query returned no result.
     */
    public function getSingleResult( $hydrationMode = null ): mixed
    {
        $result = $this->execute( null, $hydrationMode );

        if ( $this->_hydrationMode !== self::HYDRATE_SINGLE_SCALAR && !$result ) {
            throw new NoResultException();
        }

        if ( !is_array( $result ) ) {
            return $result;
        }

        if ( count( $result ) > 1 ) {
            throw new NonUniqueResultException();
        }

        return array_shift( $result );
    }

    /**
     * Gets the single scalar result of the query.
     *
     * Alias for getSingleResult(HYDRATE_SINGLE_SCALAR).
     *
     * @return mixed The scalar result.
     *
     * @throws NoResultException        If the query returned no result.
     * @throws NonUniqueResultException If the query result is not unique.
     */
    public function getSingleScalarResult(): mixed
    {
        return $this->getSingleResult( self::HYDRATE_SINGLE_SCALAR );
    }

    /**
     * Sets a query hint. If the hint name is not recognized, it is silently ignored.
     *
     * @param string $name The name of the hint.
     * @param mixed $value The value of the hint.
     *
     * @return $this
     */
    public function setHint( $name, $value ): static
    {
        $this->_hints[ $name ] = $value;

        return $this;
    }

    /**
     * Gets the value of a query hint. If the hint name is not recognized, FALSE is returned.
     *
     * @param string $name The name of the hint.
     *
     * @return mixed The value of the hint or FALSE, if the hint name is not recognized.
     */
    public function getHint( $name ): mixed
    {
        return $this->_hints[ $name ] ?? false;
    }

    /**
     * Check if the query has a hint
     *
     * @param string $name The name of the hint
     *
     * @return bool False if the query does not have any hint
     */
    public function hasHint( $name ): bool
    {
        return isset( $this->_hints[ $name ] );
    }

    /**
     * Return the key value map of query hints that are currently set.
     *
     * @return array<string,mixed>
     */
    public function getHints(): array
    {
        return $this->_hints;
    }

    /**
     * Executes the query and returns an IterableResult that can be used to incrementally
     * iterate over the result.
     *
     * @deprecated 2.8 Use {@see toIterable} instead. See https://github.com/doctrine/orm/issues/8463
     *
     * @param ArrayCollection|mixed[]|null $parameters The query parameters.
     * @param string|int|null $hydrationMode The hydration mode to use.
     * @psalm-param ArrayCollection<int, Parameter>|array<string, mixed>|null $parameters
     * @psalm-param string|AbstractQuery::HYDRATE_*|null $hydrationMode The hydration mode to use.
     *
     * @return IterableResult
     */
    public function iterate( $parameters = null, $hydrationMode = null ): IterableResult
    {
        if ( $hydrationMode !== null ) {
            $this->setHydrationMode( $hydrationMode );
        }

        if ( !empty( $parameters ) ) {
            $this->setParameters( $parameters );
        }

        $rsm = $this->getResultSetMapping();
        if ( $rsm === null ) {
            throw new LogicException( 'Uninitialized result set mapping.' );
        }

        $stmt = $this->_doExecute();

        return $this->_em->newHydrator( $this->_hydrationMode )->iterate( $stmt, $rsm, $this->_hints );
    }

    /**
     * Executes the query and returns an iterable that can be used to incrementally
     * iterate over the result.
     *
     * @param ArrayCollection|array|mixed[] $parameters The query parameters.
     * @param string|int|null $hydrationMode The hydration mode to use.
     * @psalm-param ArrayCollection<int, Parameter>|mixed[] $parameters
     * @psalm-param string|AbstractQuery::HYDRATE_*|null $hydrationMode
     *
     * @return iterable<mixed>
     */
    public function toIterable( iterable $parameters = [], $hydrationMode = null ): iterable
    {
        if ( $hydrationMode !== null ) {
            $this->setHydrationMode( $hydrationMode );
        }

        if (
            ( $this->isCountable( $parameters ) && count( $parameters ) !== 0 )
            || ( $parameters instanceof Traversable && iterator_count( $parameters ) !== 0 )
        ) {
            $this->setParameters( $parameters );
        }

        $rsm = $this->getResultSetMapping();
        if ( $rsm === null ) {
            throw new LogicException( 'Uninitialized result set mapping.' );
        }

        if ( $rsm->isMixed && count( $rsm->scalarMappings ) > 0 ) {
            throw QueryException::iterateWithMixedResultNotAllowed();
        }

        $stmt = $this->_doExecute();

        return $this->_em->newHydrator( $this->_hydrationMode )->toIterable( $stmt, $rsm, $this->_hints );
    }

    /**
     * Executes the query.
     *
     * @param ArrayCollection|mixed[]|null $parameters Query parameters.
     * @param string|int|null $hydrationMode Processing mode to be used during the hydration process.
     * @psalm-param ArrayCollection<int, Parameter>|mixed[]|null $parameters
     * @psalm-param string|AbstractQuery::HYDRATE_*|null $hydrationMode
     *
     * @return mixed
     */
    public function execute( $parameters = null, $hydrationMode = null ): mixed
    {
        return $this->executeIgnoreQueryCache( $parameters, $hydrationMode );
    }

    /**
     * Execute query ignoring second level cache.
     *
     * @param ArrayCollection|mixed[]|null $parameters
     * @param string|int|null $hydrationMode
     * @psalm-param ArrayCollection<int, Parameter>|mixed[]|null $parameters
     * @psalm-param string|AbstractQuery::HYDRATE_*|null $hydrationMode
     *
     * @return mixed
     */
    private function executeIgnoreQueryCache( $parameters = null, $hydrationMode = null ): mixed
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
        if ( $rsm === null ) {
            throw new LogicException( 'Uninitialized result set mapping.' );
        }

        $data = $this->_em->newHydrator( $this->_hydrationMode )->hydrateAll( $stmt, $rsm, $this->_hints );

        return $data;
    }

    /**
     * Executes the query and returns a resulting Statement object.
     *
     * @return Result|int The executed database statement that holds
     *                    the results, or an integer indicating how
     *                    many rows were affected.
     */
    abstract protected function _doExecute(): int|Result;

    /**
     * Cleanup Query resource when clone is called.
     *
     * @return void
     */
    public function __clone()
    {
        $this->parameters = new ArrayCollection();

        $this->_hints = $this->_em->getConfiguration()->getDefaultQueryHints();
    }

    /** @param iterable<mixed> $subject */
    private function isCountable( iterable $subject ): bool
    {
        return $subject instanceof Countable || is_array( $subject );
    }
}
