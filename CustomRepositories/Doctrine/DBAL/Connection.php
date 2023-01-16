<?php /** @noinspection PhpInternalEntityUsedInspection */
declare( strict_types=1 );

namespace Doctrine\DBAL;

use Closure;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\SQL\Parser;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;
use JetBrains\PhpStorm\ExpectedValues;
use LogicException;
use PHP_SF\System\Core\Cache\DoctrineResultCache;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Throwable;
use Traversable;
use function array_key_exists;
use function assert;
use function count;
use function get_class;
use function implode;
use function is_int;
use function is_string;
use function key;
use function method_exists;
use function sprintf;

/**
 * A database abstraction-level connection that implements features like events, transaction isolation levels,
 * configuration, emulated transaction nesting, lazy connecting and more.
 *
 * @psalm-import-type Params from DriverManager
 * @psalm-consistent-constructor
 */
final class Connection
{
    /**
     * Represents an array of ints to be expanded by Doctrine SQL parsing.
     */
    public const PARAM_INT_ARRAY = ParameterType::INTEGER + self::ARRAY_PARAM_OFFSET;

    /**
     * Represents an array of strings to be expanded by Doctrine SQL parsing.
     */
    public const PARAM_STR_ARRAY = ParameterType::STRING + self::ARRAY_PARAM_OFFSET;

    /**
     * Represents an array of ascii strings to be expanded by Doctrine SQL parsing.
     */
    public const PARAM_ASCII_STR_ARRAY = ParameterType::ASCII + self::ARRAY_PARAM_OFFSET;

    /**
     * Offset by which PARAM_* constants are detected as arrays of the param type.
     *
     * @internal Should be used only within the wrapper layer.
     */
    public const ARRAY_PARAM_OFFSET = 100;

    /**
     * The wrapped driver connection.
     *
     * @var DriverConnection|null
     */
    private DriverConnection|null $_conn;

    private Configuration $_config;

    /**
     * @deprecated
     *
     * @var EventManager
     */
    private EventManager $_eventManager;

    /**
     * The current auto-commit mode of this connection.
     */
    private bool $autoCommit;

    /**
     * The transaction nesting level.
     */
    private int $transactionNestingLevel = 0;

    /**
     * The currently active transaction isolation level or NULL before it has been determined.
     *
     * @var TransactionIsolationLevel::*|null
     */
    private $transactionIsolationLevel;

    /**
     * If nested transactions should use savepoints.
     */
    private bool $nestTransactionsWithSavepoints = false;

    /**
     * The parameters used during creation of the Connection instance.
     *
     * @var array<string,mixed>
     * @psalm-var Params
     */
    private array $params;

    /**
     * The database platform object used by the connection or NULL before it's initialized.
     */
    private AbstractPlatform|null $platform = null;

    private ExceptionConverter|null $exceptionConverter = null;
    private Parser|null $parser = null;

    /**
     * The schema manager.
     *
     * @deprecated Use {@see createSchemaManager()} instead.
     *
     * @var AbstractSchemaManager|null
     */
    private AbstractSchemaManager|null $_schemaManager;

    /**
     * The used DBAL driver.
     *
     * @var Driver
     */
    private Driver $_driver;

    /**
     * Flag that indicates whether the current transaction is marked for rollback only.
     */
    private bool $isRollbackOnly = false;

    /**
     * Initializes a new instance of the Connection class.
     *
     * @param array<string,mixed> $params The connection parameters.
     * @param Driver $driver The driver to use.
     * @param Configuration|null $config The configuration, optional.
     * @param EventManager|null $eventManager The event manager, optional.
     * @psalm-param Params $params
     *
     * @throws Exception
     * @internal The connection can be only instantiated by the driver manager.
     *
     */
    public function __construct( array $params, Driver $driver, Configuration|null $config = null, EventManager|null $eventManager = null )
    {
        $this->_driver = $driver;
        $this->params = $params;

        // Create default config and event manager if none given
        $config ??= new Configuration();
        $eventManager ??= new EventManager();

        $this->_config = $config;
        $this->_eventManager = $eventManager;

        if ( isset( $params['platform'] ) ) {
            if ( !$params['platform'] instanceof Platforms\AbstractPlatform )
                throw new InvalidConfigurationException( "Invalid platform type '" . $params['platform'] . "'!" );

            $this->platform = $params['platform'];
            $this->platform->setEventManager( $this->_eventManager );
        }

        $this->autoCommit = $config->getAutoCommit();
    }

    /**
     * Gets the parameters used during instantiation.
     *
     * @return array<string,mixed>
     * @psalm-return Params
     * @internal
     *
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Gets the name of the currently selected database.
     *
     * @return string|null The name of the database or NULL if a database is not selected.
     *                     The platforms which don't support the concept of a database (e.g. embedded databases)
     *                     must always return a string as an indicator of an implicitly selected database.
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function getDatabase(): string|null
    {
        $platform = $this->getDatabasePlatform();
        $query = $platform?->getDummySelectSQL( $platform?->getCurrentDatabaseExpression() );
        $database = $this->fetchOne( $query );

        assert( is_string( $database ) || $database === null );

        return $database;
    }

    /**
     * Gets the DBAL driver instance.
     */
    public function getDriver(): Driver
    {
        return $this->_driver;
    }

    /**
     * Gets the Configuration used by the Connection.
     */
    public function getConfiguration(): Configuration
    {
        return $this->_config;
    }

    /**
     * Gets the EventManager used by the Connection.
     *
     * @deprecated
     *
     */
    public function getEventManager(): EventManager
    {
        return $this->_eventManager;
    }

    /**
     * Gets the DatabasePlatform for the connection.
     *
     * @throws Exception
     * @throws Exception
     */
    public function getDatabasePlatform(): AbstractPlatform
    {
        if ( $this->platform === null ) {
            $this->platform = $this->detectDatabasePlatform();
            $this->platform->setEventManager( $this->_eventManager );
        }

        return $this->platform;
    }

    /**
     * Creates an expression builder for the connection.
     */
    public function createExpressionBuilder(): ExpressionBuilder
    {
        return new ExpressionBuilder( $this );
    }

    /**
     * Establishes the connection with the database.
     *
     * @return bool TRUE if the connection was successfully established, FALSE if
     *              the connection is already open.
     *
     * @throws Exception
     * @throws Exception
     * @internal This method will be made protected in DBAL 4.0.
     *
     */
    public function connect(): bool
    {
        if ( $this->_conn !== null )
            return false;

        try {
            $this->_conn = $this->_driver->connect( $this->params );
        } catch ( Driver\Exception $e ) {
            throw $this->convertException( $e );
        }

        if ( $this->autoCommit === false )
            $this->beginTransaction();

        return true;
    }

    /**
     * Detects and sets the database platform.
     *
     * Evaluates custom platform class and version in order to set the correct platform.
     *
     * @throws Exception If an invalid platform was specified for this connection.
     * @throws Exception
     * @throws Throwable
     */
    private function detectDatabasePlatform(): AbstractPlatform
    {
        $version = $this->getDatabasePlatformVersion();

        if ( $version !== null ) {
            assert( $this->_driver instanceof VersionAwarePlatformDriver );

            return $this->_driver->createDatabasePlatformForVersion( $version );
        }

        return $this->_driver->getDatabasePlatform();
    }

    /**
     * Returns the version of the related platform if applicable.
     *
     * Returns null if either the driver is not capable to create version
     * specific platform instances, no explicit server version was specified
     * or the underlying driver connection cannot determine the platform
     * version without having to query it (performance reasons).
     *
     * @return string|null
     *
     * @throws Throwable
     */
    private function getDatabasePlatformVersion(): string|null
    {
        // Driver does not support version specific platforms.
        if ( !$this->_driver instanceof VersionAwarePlatformDriver ) {
            return null;
        }

        // Explicit platform version requested (supersedes auto-detection).
        if ( isset( $this->params['serverVersion'] ) ) {
            return $this->params['serverVersion'];
        }

        // If not connected, we need to connect now to determine the platform version.
        if ( $this->_conn === null ) {
            try {
                $this->connect();
            } catch ( Exception $originalException ) {
                if ( !isset( $this->params['dbname'] ) ) {
                    throw $originalException;
                }

                // The database to connect to might not yet exist.
                // Retry detection without database name connection parameter.
                $params = $this->params;

                unset( $this->params['dbname'] );

                try {
                    $this->connect();
                } catch ( Exception $fallbackException ) {
                    // Either the platform does not support database-less connections
                    // or something else went wrong.
                    throw $originalException;
                } finally {
                    $this->params = $params;
                }

                $serverVersion = $this->getServerVersion();

                // Close "temporary" connection to allow connecting to the real database again.
                $this->close();

                return $serverVersion;
            }
        }

        return $this->getServerVersion();
    }

    /**
     * Returns the database server version if the underlying driver supports it.
     *
     * @return string|null
     *
     * @throws Exception
     * @throws Exception
     */
    private function getServerVersion(): string|null
    {
        $connection = $this->getNativeConnection();

        // Automatic platform version detection.
        if ( $connection instanceof ServerInfoAwareConnection ) {
            try {
                return $connection->getServerVersion();
            } catch ( Driver\Exception $e ) {
                throw $this->convertException( $e );
            }
        }

        return null;
    }

    /**
     * Returns the current auto-commit mode for this connection.
     *
     * @return bool True if auto-commit mode is currently enabled for this connection, false otherwise.
     * @see    setAutoCommit
     *
     */
    public function isAutoCommit(): bool
    {
        return $this->autoCommit === true;
    }

    /**
     * Sets auto-commit mode for this connection.
     *
     * If a connection is in auto-commit mode, then all its SQL statements will be executed and committed as individual
     * transactions. Otherwise, its SQL statements are grouped into transactions that are terminated by a call to either
     * the method commit or the method rollback. By default, new connections are in auto-commit mode.
     *
     * NOTE: If this method is called during a transaction and the auto-commit mode is changed, the transaction is
     * committed. If this method is called and the auto-commit mode is not changed, the call is a no-op.
     *
     * @param bool $autoCommit True to enable auto-commit mode; false to disable it.
     *
     * @return void
     * @see   isAutoCommit
     *
     */
    public function setAutoCommit( bool $autoCommit ): void
    {
        // Mode not changed, no-op.
        if ( $autoCommit === $this->autoCommit )
            return;

        $this->autoCommit = $autoCommit;

        // Commit all currently active transactions if any when switching auto-commit mode.
        if ( $this->_conn === null || $this->transactionNestingLevel === 0 )
            return;

        $this->commitAll();
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as an associative array.
     *
     * @param string $query SQL query
     * @param list<mixed>|array<string, mixed> $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return array<string, mixed>|false False is returned if no rows are found.
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function fetchAssociative( string $query, array $params = [], array $types = [] ): array|false
    {
        return $this->executeQuery( $query, $params, $types )->fetchAssociative();
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as a numerically indexed array.
     *
     * @param string $query SQL query
     * @param list<mixed>|array<string, mixed> $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return list<mixed>|false False is returned if no rows are found.
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function fetchNumeric( string $query, array $params = [], array $types = [] ): array|false
    {
        return $this->executeQuery( $query, $params, $types )->fetchNumeric();
    }

    /**
     * Prepares and executes an SQL query and returns the value of a single column
     * of the first row of the result.
     *
     * @param string $query SQL query
     * @param list<mixed>|array<string, mixed> $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return mixed|false False is returned if no rows are found.
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function fetchOne( string $query, array $params = [], array $types = [] ): mixed
    {
        return $this->executeQuery( $query, $params, $types )->fetchOne();
    }

    /**
     * Whether an actual connection to the database is established.
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->_conn !== null;
    }

    /**
     * Checks whether a transaction is currently active.
     *
     * @return bool TRUE if a transaction is currently active, FALSE otherwise.
     */
    public function isTransactionActive(): bool
    {
        return $this->transactionNestingLevel > 0;
    }

    /**
     * Adds condition based on the criteria to the query components
     *
     * @param array<string,mixed> $criteria Map of key columns to their values
     * @param string[] $columns Column names
     * @param mixed[] $values Column values
     * @param string[] $conditions Key conditions
     *
     * @throws Exception
     * @throws Exception
     */
    private function addCriteriaCondition( array $criteria, array &$columns, array &$values, array &$conditions ): void
    {
        $platform = $this->getDatabasePlatform();

        foreach ( $criteria as $columnName => $value ) {
            if ( $value === null ) {
                $conditions[] = $platform->getIsNullExpression( $columnName );
                continue;
            }

            $columns[] = $columnName;
            $values[] = $value;
            $conditions[] = $columnName . ' = ?';
        }
    }

    /**
     * Executes an SQL DELETE statement on a table.
     *
     * Table expression and columns are not escaped and are not safe for user-input.
     *
     * @param string $table Table name
     * @param array<string, mixed> $criteria Deletion criteria
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return int|string The number of affected rows.
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function delete( string $table, array $criteria, array $types = [] ): int|string
    {
        if ( count( $criteria ) === 0 )
            throw InvalidArgumentException::fromEmptyCriteria();

        $columns = $values = $conditions = [];

        $this->addCriteriaCondition( $criteria, $columns, $values, $conditions );

        return $this->executeStatement(
            'DELETE FROM ' . $table . ' WHERE ' . implode( ' AND ', $conditions ),
            $values,
            is_string( key( $types ) ) ? $this->extractTypeValues( $columns, $types ) : $types,
        );
    }

    /**
     * Closes the connection.
     *
     * @return void
     */
    public function close(): void
    {
        $this->_conn = null;
        $this->transactionNestingLevel = 0;
    }

    /**
     * Sets the transaction isolation level.
     *
     * @param TransactionIsolationLevel::* $level The level to set.
     *
     * @return int|string
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function setTransactionIsolation( #[ExpectedValues( valuesFromClass: TransactionIsolationLevel::class )] int $level ): int|string
    {
        $this->transactionIsolationLevel = $level;

        return $this->executeStatement( $this->getDatabasePlatform()->getSetTransactionIsolationSQL( $level ) );
    }

    /**
     * Gets the currently active transaction isolation level.
     *
     * @return TransactionIsolationLevel::* The current transaction isolation level.
     *
     * @throws Exception
     * @throws Exception
     */
    public function getTransactionIsolation(): int
    {
        return $this->transactionIsolationLevel ??= $this->getDatabasePlatform()->getDefaultTransactionIsolationLevel();
    }

    /**
     * Executes an SQL UPDATE statement on a table.
     *
     * Table expression and columns are not escaped and are not safe for user-input.
     *
     * @param string $table Table name
     * @param array<string, mixed> $data Column-value pairs
     * @param array<string, mixed> $criteria Update criteria
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return int|string The number of affected rows.
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function update( string $table, array $data, array $criteria, array $types = [] ): int|string
    {
        $columns = $values = $conditions = $set = [];

        foreach ( $data as $columnName => $value ) {
            $columns[] = $columnName;
            $values[] = $value;
            $set[] = $columnName . ' = ?';
        }

        $this->addCriteriaCondition( $criteria, $columns, $values, $conditions );

        if ( is_string( key( $types ) ) )
            $types = $this->extractTypeValues( $columns, $types );

        $sql = 'UPDATE ' . $table . ' SET ' . implode( ', ', $set )
            . ' WHERE ' . implode( ' AND ', $conditions );

        return $this->executeStatement( $sql, $values, $types );
    }

    /**
     * Inserts a table row with specified data.
     *
     * Table expression and columns are not escaped and are not safe for user-input.
     *
     * @param string $table Table name
     * @param array<string, mixed> $data Column-value pairs
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return int|string The number of affected rows.
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function insert( string $table, array $data, array $types = [] ): int|string
    {
        if ( count( $data ) === 0 )
            return $this->executeStatement( 'INSERT INTO ' . $table . ' () VALUES ()' );

        $columns = [];
        $values = [];
        $set = [];

        foreach ( $data as $columnName => $value ) {
            $columns[] = $columnName;
            $values[] = $value;
            $set[] = '?';
        }

        return $this->executeStatement(
            'INSERT INTO ' . $table . ' (' . implode( ', ', $columns ) . ')' .
            ' VALUES (' . implode( ', ', $set ) . ')',
            $values,
            is_string( key( $types ) ) ? $this->extractTypeValues( $columns, $types ) : $types,
        );
    }

    /**
     * Extract ordered type list from an ordered column list and type map.
     *
     * @param array<int, string> $columnList
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     *
     * @return array<int, int|string|Type|null>|array<string, int|string|Type|null>
     */
    private function extractTypeValues( array $columnList, array $types ): array
    {
        $typeValues = [];

        foreach ( $columnList as $columnName ) {
            $typeValues[] = $types[ $columnName ] ?? ParameterType::STRING;
        }

        return $typeValues;
    }

    /**
     * Quotes a string so it can be safely used as a table or column name, even if
     * it is a reserved name.
     *
     * Delimiting style depends on the underlying database platform that is being used.
     *
     * NOTE: Just because you CAN use quoted identifiers does not mean
     * you SHOULD use them. In general, they end up causing way more
     * problems than they solve.
     *
     * @param string $str The name to be quoted.
     *
     * @return string The quoted name.
     */
    public function quoteIdentifier( string $str ): string
    {
        return $this->getDatabasePlatform()->quoteIdentifier( $str );
    }

    /**
     * The usage of this method is discouraged. Use prepared statements
     * or {@see AbstractPlatform::quoteStringLiteral()} instead.
     *
     * @param mixed $value
     * @param int|string|Type|null $type
     *
     * @return mixed
     * @throws Exception
     */
    public function quote( mixed $value, int|string|Type|null $type = ParameterType::STRING ): mixed
    {
        $connection = $this->getNativeConnection();

        [ $value, $bindingType ] = $this->getBindingInfo( $value, $type );

        return $connection->quote( $value, $bindingType );
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of numeric arrays.
     *
     * @param string $query SQL query
     * @param list<mixed>|array<string, mixed> $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return list<list<mixed>>
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function fetchAllNumeric( string $query, array $params = [], array $types = [] ): array
    {
        return $this->executeQuery( $query, $params, $types )->fetchAllNumeric();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of associative arrays.
     *
     * @param string $query SQL query
     * @param list<mixed>|array<string, mixed> $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return list<array<string,mixed>>
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function fetchAllAssociative( string $query, array $params = [], array $types = [] ): array
    {
        return $this->executeQuery( $query, $params, $types )->fetchAllAssociative();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys
     * mapped to the first column and the values mapped to the second column.
     *
     * @param string $query SQL query
     * @param list<mixed>|array<string, mixed> $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return array<mixed,mixed>
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function fetchAllKeyValue( string $query, array $params = [], array $types = [] ): array
    {
        return $this->executeQuery( $query, $params, $types )->fetchAllKeyValue();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys mapped
     * to the first column and the values being an associative array representing the rest of the columns
     * and their values.
     *
     * @param string $query SQL query
     * @param list<mixed>|array<string, mixed> $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return array<mixed,array<string,mixed>>
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function fetchAllAssociativeIndexed( string $query, array $params = [], array $types = [] ): array
    {
        return $this->executeQuery( $query, $params, $types )->fetchAllAssociativeIndexed();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of the first column values.
     *
     * @param string $query SQL query
     * @param list<mixed>|array<string, mixed> $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return list<mixed>
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function fetchFirstColumn( string $query, array $params = [], array $types = [] ): array
    {
        return $this->executeQuery( $query, $params, $types )->fetchFirstColumn();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented as numeric arrays.
     *
     * @param string $query SQL query
     * @param list<mixed>|array<string, mixed> $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return Traversable<int,list<mixed>>
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function iterateNumeric( string $query, array $params = [], array $types = [] ): Traversable
    {
        return $this->executeQuery( $query, $params, $types )->iterateNumeric();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented
     * as associative arrays.
     *
     * @param string $query SQL query
     * @param list<mixed>|array<string, mixed> $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return Traversable<int,array<string,mixed>>
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function iterateAssociative( string $query, array $params = [], array $types = [] ): Traversable
    {
        return $this->executeQuery( $query, $params, $types )->iterateAssociative();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator with the keys
     * mapped to the first column and the values mapped to the second column.
     *
     * @param string $query SQL query
     * @param list<mixed>|array<string, mixed> $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return Traversable<mixed,mixed>
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function iterateKeyValue( string $query, array $params = [], array $types = [] ): Traversable
    {
        return $this->executeQuery( $query, $params, $types )->iterateKeyValue();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator with the keys mapped
     * to the first column and the values being an associative array representing the rest of the columns
     * and their values.
     *
     * @param string $query SQL query
     * @param list<mixed>|array<string, mixed> $params Query parameters
     * @param array<int, int|string>|array<string, int|string> $types Parameter types
     *
     * @return Traversable<mixed,array<string,mixed>>
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function iterateAssociativeIndexed( string $query, array $params = [], array $types = [] ): Traversable
    {
        return $this->executeQuery( $query, $params, $types )->iterateAssociativeIndexed();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over the first column values.
     *
     * @param string $query SQL query
     * @param list<mixed>|array<string, mixed> $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return Traversable<int,mixed>
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function iterateColumn( string $query, array $params = [], array $types = [] ): Traversable
    {
        return $this->executeQuery( $query, $params, $types )->iterateColumn();
    }

    /**
     * Prepares an SQL statement.
     *
     * @param string $sql The SQL statement to prepare.
     *
     * @throws Exception
     * @throws Exception
     */
    public function prepare( string $sql ): Statement
    {
        $connection = $this->getNativeConnection();

        try {
            $statement = $connection->prepare( $sql );
        } catch ( Driver\Exception $e ) {
            throw $this->convertExceptionDuringQuery( $e, $sql );
        }

        return new Statement( $this, $statement, $sql );
    }

    /**
     * Executes an optionally parametrized, SQL query.
     *
     * If the query is parametrized, a prepared statement is used.
     * If an SQLLogger is configured, the execution is logged.
     *
     * @param string $sql SQL query
     * @param list<mixed>|array<string, mixed> $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @throws Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws Exception
     */
    public function executeQuery( string $sql, array $params = [], array $types = [] ): Result
    {
        $connection = $this->getNativeConnection();

        try {
            $unHashedKey = $sql . ':params=' . serialize( $params ) . ':types=' . serialize( $types );
            $key = 'doctrine_result_cache:' . hash( 'sha256', $unHashedKey );

            if ( count( $params ) > 0 ) {
                if ( $this->needsArrayParameterConversion( $params, $types ) ) {
                    [ $sql, $params, $types ] = $this->expandArrayParameters( $sql, $params, $types );
                }

                $stmt = $connection->prepare( $sql );

                $this->bindParameters( $stmt, $params, $types );

                if ( ra()->has( $key ) === null )
                    ra()->set( $key, j_encode( $stmt->execute()->fetchAllAssociative() ) );

            } elseif ( ra()->has( $key ) === null )
                ra()->set( $key, j_encode( $connection->query( $sql )->fetchAllAssociative() ) );

            $result = new DoctrineResultCache( $key, $unHashedKey );

            return new Result( $result, $this );
        } catch ( Driver\Exception $e ) {
            throw $this->convertExceptionDuringQuery( $e, $sql, $params, $types );
        }
    }


    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be used for:
     *  - DML statements: INSERT, UPDATE, DELETE, etc.
     *  - DDL statements: CREATE, DROP, ALTER, etc.
     *  - DCL statements: GRANT, REVOKE, etc.
     *  - Session control statements: ALTER SESSION, SET, DECLARE, etc.
     *  - Other statements that don't yield a row set.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string $sql SQL statement
     * @param list<mixed>|array<string, mixed> $params Statement parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return int|string The number of affected rows.
     *
     * @throws Exception
     * @throws Exception
     */
    public function executeStatement( string $sql, array $params = [], array $types = [] ): int|string
    {
        $connection = $this->getNativeConnection();

        try {
            if ( count( $params ) > 0 ) {
                if ( $this->needsArrayParameterConversion( $params, $types ) ) {
                    [ $sql, $params, $types ] = $this->expandArrayParameters( $sql, $params, $types );
                }

                $stmt = $connection->prepare( $sql );

                $this->bindParameters( $stmt, $params, $types );

                return $stmt->execute()->rowCount();
            }

            return $connection->exec( $sql );
        } catch ( Driver\Exception $e ) {
            throw $this->convertExceptionDuringQuery( $e, $sql, $params, $types );
        }
    }

    /**
     * Returns the current transaction nesting level.
     *
     * @return int The nesting level. A value of 0 means there's no active transaction.
     */
    public function getTransactionNestingLevel(): int
    {
        return $this->transactionNestingLevel;
    }

    /**
     * Returns the ID of the last inserted row, or the last value from a sequence object,
     * depending on the underlying driver.
     *
     * Note: This method may not return a meaningful or consistent result across different drivers,
     * because the underlying database may not even support the notion of AUTO_INCREMENT/IDENTITY
     * columns or sequences.
     *
     * @param string|null $name Name of the sequence object from which the ID should be returned.
     *
     * @return string|int|false A string representation of the last inserted ID.
     *
     * @throws Exception
     */
    public function lastInsertId( string $name = null ): false|int|string
    {
        try {
            return $this->getNativeConnection()->lastInsertId( $name );
        } catch ( Driver\Exception $e ) {
            throw $this->convertException( $e );
        }
    }

    /**
     * Executes a function in a transaction.
     *
     * The function gets passed this Connection instance as an (optional) parameter.
     *
     * If an exception occurs during execution of the function or transaction commit,
     * the transaction is rolled back and the exception re-thrown.
     *
     * @param Closure(self):T $func The function to execute transactionally.
     *
     * @return T The value returned by $func
     *
     * @throws Throwable
     *
     * @template T
     */
    public function transactional( Closure $func ): Closure
    {
        $this->beginTransaction();
        try {
            $res = $func( $this );
            $this->commit();

            return $res;
        } catch ( Throwable $e ) {
            $this->rollBack();

            throw $e;
        }
    }

    /**
     * Sets if nested transactions should use savepoints.
     *
     * @param bool $nestTransactionsWithSavepoints
     *
     * @return void
     *
     * @throws Exception
     * @throws ConnectionException
     * @throws Exception
     * @throws ConnectionException
     */
    public function setNestTransactionsWithSavepoints( bool $nestTransactionsWithSavepoints ): void
    {
        if ( $this->transactionNestingLevel > 0 )
            throw ConnectionException::mayNotAlterNestedTransactionWithSavepointsInTransaction();

        if ( !$this->getDatabasePlatform()->supportsSavepoints() )
            throw ConnectionException::savepointsNotSupported();

        $this->nestTransactionsWithSavepoints = $nestTransactionsWithSavepoints;
    }

    /**
     * Gets if nested transactions should use savepoints.
     *
     * @return bool
     */
    public function getNestTransactionsWithSavepoints(): bool
    {
        return $this->nestTransactionsWithSavepoints;
    }

    /**
     * Returns the savepoint name to use for nested transactions.
     *
     * @return string
     */
    private function _getNestedTransactionSavePointName(): string
    {
        return 'DOCTRINE2_SAVEPOINT_' . $this->transactionNestingLevel;
    }

    /**
     * @return bool
     *
     * @throws Exception
     * @throws Exception
     */
    public function beginTransaction(): bool
    {
        $connection = $this->getNativeConnection();

        $this->transactionNestingLevel++;

        if ( $this->transactionNestingLevel === 1 )
            $connection->beginTransaction();

        elseif ( $this->nestTransactionsWithSavepoints )
            $this->createSavepoint( $this->_getNestedTransactionSavePointName() );

        return true;
    }

    /**
     * @return bool
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     * @throws Exception
     * @throws ConnectionException
     * @throws ConnectionException
     */
    public function commit(): bool
    {
        if ( $this->transactionNestingLevel === 0 )
            throw ConnectionException::noActiveTransaction();

        if ( $this->isRollbackOnly )
            throw ConnectionException::commitFailedRollbackOnly();

        $result = true;

        $connection = $this->getNativeConnection();

        if ( $this->transactionNestingLevel === 1 )
            /** @noinspection PhpParamsInspection */
            $result = $this->doCommit( $connection );

        elseif ( $this->nestTransactionsWithSavepoints )
            $this->releaseSavepoint( $this->_getNestedTransactionSavePointName() );

        $this->transactionNestingLevel--;

        if ( $this->autoCommit !== false || $this->transactionNestingLevel !== 0 ) {
            return $result;
        }

        $this->beginTransaction();

        return $result;
    }

    /**
     * @param DriverConnection $connection
     * @return bool
     *
     * @throws Exception
     */
    private function doCommit( DriverConnection $connection ): bool
    {
        return $connection->commit();
    }

    /**
     * Commits all current nesting transactions.
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    private function commitAll(): void
    {
        while ( $this->transactionNestingLevel !== 0 ) {
            if ( $this->autoCommit === false && $this->transactionNestingLevel === 1 ) {
                // When in no auto-commit mode, the last nesting commit immediately starts a new transaction.
                // Therefore we need to do the final commit here and then leave to avoid an infinite loop.
                $this->commit();

                return;
            }

            $this->commit();
        }
    }

    /**
     * Cancels any database changes done during the current transaction.
     *
     * @return bool
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     * @throws ConnectionException
     */
    public function rollBack(): bool
    {
        if ( $this->transactionNestingLevel === 0 )
            throw ConnectionException::noActiveTransaction();

        $connection = $this->getNativeConnection();

        if ( $this->transactionNestingLevel === 1 ) {
            $this->transactionNestingLevel = 0;
            $connection->rollBack();
            $this->isRollbackOnly = false;

            if ( $this->autoCommit === false ) {
                $this->beginTransaction();
            }
        } elseif ( $this->nestTransactionsWithSavepoints ) {

            $this->rollbackSavepoint( $this->_getNestedTransactionSavePointName() );
            $this->transactionNestingLevel--;
        } else {
            $this->isRollbackOnly = true;
            $this->transactionNestingLevel--;
        }

        return true;
    }

    /**
     * Creates a new savepoint.
     *
     * @param string $savepoint The name of the savepoint to create.
     *
     * @return void
     *
     * @throws ConnectionException
     */
    public function createSavepoint( string $savepoint ): void
    {
        $platform = $this->getDatabasePlatform();

        if ( !$platform->supportsSavepoints() )
            throw ConnectionException::savepointsNotSupported();

        $this->executeStatement( $platform->createSavePoint( $savepoint ) );
    }

    /**
     * Releases the given savepoint.
     *
     * @param string $savepoint The name of the savepoint to release.
     *
     * @return void
     *
     * @throws Exception
     * @throws Exception
     * @throws ConnectionException
     * @throws Exception
     */
    public function releaseSavepoint( string $savepoint ): void
    {
        $platform = $this->getDatabasePlatform();

        if ( !$platform->supportsSavepoints() )
            throw ConnectionException::savepointsNotSupported();

        if ( !$platform->supportsReleaseSavepoints() )
            return;

        $this->executeStatement( $platform->releaseSavePoint( $savepoint ) );
    }

    /**
     * Rolls back to the given savepoint.
     *
     * @param string $savepoint The name of the savepoint to rollback to.
     *
     * @return void
     *
     * @throws Exception
     * @throws Exception
     * @throws ConnectionException
     * @throws Exception
     */
    public function rollbackSavepoint( string $savepoint ): void
    {
        $platform = $this->getDatabasePlatform();

        if ( !$platform->supportsSavepoints() )
            throw ConnectionException::savepointsNotSupported();

        $this->executeStatement( $platform->rollbackSavePoint( $savepoint ) );
    }

    public function getNativeConnection(): object
    {
        $this->connect();

        assert( $this->_conn !== null );
        if ( !method_exists( $this->_conn, 'getNativeConnection' ) ) {
            throw new LogicException( sprintf(
                'The driver connection %s does not support accessing the native connection.',
                get_class( $this->_conn ),
            ) );
        }

        return $this->_conn->getNativeConnection();
    }

    /**
     * Creates a SchemaManager that can be used to inspect or change the
     * database schema through the connection.
     *
     * @throws Exception
     * @throws Exception
     */
    public function createSchemaManager(): AbstractSchemaManager
    {
        return $this->_driver->getSchemaManager(
            $this,
            $this->getDatabasePlatform(),
        );
    }

    /**
     * Marks the current transaction so that the only possible
     * outcome for the transaction to be rolled back.
     *
     * @return void
     *
     * @throws ConnectionException If no transaction is active.
     */
    public function setRollbackOnly(): void
    {
        if ( $this->transactionNestingLevel === 0 )
            throw ConnectionException::noActiveTransaction();

        $this->isRollbackOnly = true;
    }

    /**
     * Checks whether the current transaction is marked for rollback only.
     *
     * @return bool
     *
     * @throws ConnectionException If no transaction is active.
     */
    public function isRollbackOnly(): bool
    {
        if ( $this->transactionNestingLevel === 0 )
            throw ConnectionException::noActiveTransaction();

        return $this->isRollbackOnly;
    }

    /**
     * Converts a given value to its database representation according to the conversion
     * rules of a specific DBAL mapping type.
     *
     * @param mixed $value The value to convert.
     * @param string $type The name of the DBAL mapping type.
     *
     * @return mixed The converted value.
     *
     * @throws Exception
     * @throws Exception
     * @throws ConversionException
     * @throws Exception
     */
    public function convertToDatabaseValue( mixed $value, string $type ): mixed
    {
        return Type::getType( $type )->convertToDatabaseValue( $value, $this->getDatabasePlatform() );
    }

    /**
     * Converts a given value to its PHP representation according to the conversion
     * rules of a specific DBAL mapping type.
     *
     * @param mixed $value The value to convert.
     * @param string $type The name of the DBAL mapping type.
     *
     * @return mixed The converted type.
     *
     * @throws Exception
     * @throws Exception
     * @throws ConversionException
     * @throws Exception
     */
    public function convertToPHPValue( mixed $value, string $type ): mixed
    {
        return Type::getType( $type )->convertToPHPValue( $value, $this->getDatabasePlatform() );
    }

    /**
     * Binds a set of parameters, some or all of which are typed with a PDO binding type
     * or DBAL mapping type, to a given statement.
     *
     * @param DriverStatement $stmt Prepared statement
     * @param list<mixed>|array<string, mixed> $params Statement parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    private function bindParameters( DriverStatement $stmt, array $params, array $types ): void
    {
        // Check whether parameters are positional or named. Mixing is not allowed.
        if ( is_int( key( $params ) ) ) {
            $bindIndex = 1;

            foreach ( $params as $key => $value ) {
                if ( isset( $types[ $key ] ) ) {
                    $type = $types[ $key ];
                    [ $value, $bindingType ] = $this->getBindingInfo( $value, $type );
                } else {
                    if ( array_key_exists( $key, $types ) ) {
                        Deprecation::trigger(
                            'doctrine/dbal',
                            'https://github.com/doctrine/dbal/pull/5550',
                            'Using NULL as prepared statement parameter type is deprecated.'
                            . 'Omit or use Parameter::STRING instead',
                        );
                    }

                    $bindingType = ParameterType::STRING;
                }

                $stmt->bindValue( $bindIndex, $value, $bindingType );

                $bindIndex++;
            }
        } else {
            // Named parameters
            foreach ( $params as $name => $value ) {
                if ( isset( $types[ $name ] ) ) {
                    $type = $types[ $name ];
                    [ $value, $bindingType ] = $this->getBindingInfo( $value, $type );
                } else {
                    if ( array_key_exists( $name, $types ) ) {
                        Deprecation::trigger(
                            'doctrine/dbal',
                            'https://github.com/doctrine/dbal/pull/5550',
                            'Using NULL as prepared statement parameter type is deprecated.'
                            . 'Omit or use Parameter::STRING instead',
                        );
                    }

                    $bindingType = ParameterType::STRING;
                }

                $stmt->bindValue( $name, $value, $bindingType );
            }
        }
    }

    /**
     * Gets the binding type of a given type.
     *
     * @param mixed $value The value to bind.
     * @param int|string|Type|null $type The type to bind (PDO or DBAL).
     *
     * @return array{mixed, int} [0] => the (escaped) value, [1] => the binding type.
     *
     * @throws Exception
     * @throws Exception
     * @throws ConversionException
     * @throws Exception
     */
    private function getBindingInfo( mixed $value, int|string|Type|null $type ): array
    {
        if ( is_string( $type ) )
            $type = Type::getType( $type );

        if ( $type instanceof Type ) {
            $value = $type->convertToDatabaseValue( $value, $this->getDatabasePlatform() );
            $bindingType = $type->getBindingType();
        } else
            $bindingType = $type ?? ParameterType::STRING;

        return [ $value, $bindingType ];
    }

    /**
     * Creates a new instance of a SQL query builder.
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder(): QueryBuilder
    {
        return new Query\QueryBuilder( $this );
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     * @internal
     *
     */
    public function convertExceptionDuringQuery( Driver\Exception $e, string $sql, array $params = [], array $types = [] ): DriverException
    {
        return $this->handleDriverException( $e, new Query( $sql, $params, $types ) );
    }

    public function convertException( Driver\Exception $e ): DriverException
    {
        return $this->handleDriverException( $e, null );
    }

    /**
     * @param array<int, mixed>|array<string, mixed> $params
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     *
     * @return array{string, list<mixed>, array<int,Type|int|string|null>}
     * @throws Parser\Exception
     */
    private function expandArrayParameters( string $sql, array $params, array $types ): array
    {
        $this->parser ??= $this->getDatabasePlatform()->createSQLParser();
        $visitor = new ExpandArrayParameters( $params, $types );

        $this->parser->parse( $sql, $visitor );

        return [
            $visitor->getSQL(),
            $visitor->getParameters(),
            $visitor->getTypes(),
        ];
    }

    /**
     * @param array<int, mixed>|array<string, mixed> $params
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     */
    private function needsArrayParameterConversion( array $params, array $types ): bool
    {
        if ( is_string( key( $params ) ) ) {
            return true;
        }

        foreach ( $types as $type ) {
            if (
                $type === self::PARAM_INT_ARRAY
                || $type === self::PARAM_STR_ARRAY
                || $type === self::PARAM_ASCII_STR_ARRAY
            ) {
                return true;
            }
        }

        return false;
    }

    private function handleDriverException( Driver\Exception $driverException, Query|null $query ): DriverException
    {
        $this->exceptionConverter ??= $this->_driver->getExceptionConverter();
        $exception = $this->exceptionConverter->convert( $driverException, $query );

        if ( $exception instanceof ConnectionLost ) {
            $this->close();
        }

        return $exception;
    }

    /**
     * BC layer for a wide-spread use-case of old DBAL APIs
     *
     * @param array<mixed> $params The query parameters
     * @param array<int|string|null> $types The parameter types
     * @deprecated This API is deprecated and will be removed after 2022
     *
     */
    public function executeUpdate( string $sql, array $params = [], array $types = [] ): int
    {
        return $this->executeStatement( $sql, $params, $types );
    }

    /**
     * BC layer for a wide-spread use-case of old DBAL APIs
     *
     * @deprecated This API is deprecated and will be removed after 2022
     */
    public function query( string $sql ): Result
    {
        return $this->executeQuery( $sql );
    }
}
