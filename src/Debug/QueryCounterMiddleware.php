<?php declare(strict_types=1);

namespace PHP_SF\System\Debug;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

final class QueryCounterMiddleware implements MiddlewareInterface
{
    /**
     * @var array<string, int>
     */
    private static array $counts = [];


    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(array $params): DriverConnection
            {
                $key = $params['dbname'] ?? 'default';

                return new class(parent::connect($params), $key) extends AbstractConnectionMiddleware {
                    public function __construct(DriverConnection $connection, private readonly string $key)
                    {
                        parent::__construct($connection);
                    }

                    public function prepare(string $sql): Statement
                    {
                        QueryCounterMiddleware::increment($this->key);

                        return parent::prepare($sql);
                    }

                    public function query(string $sql): Result
                    {
                        QueryCounterMiddleware::increment($this->key);

                        return parent::query($sql);
                    }

                    public function exec(string $sql): int|string
                    {
                        QueryCounterMiddleware::increment($this->key);

                        return parent::exec($sql);
                    }
                };
            }
        };
    }

    public static function increment(string $key): void
    {
        self::$counts[$key] = (self::$counts[$key] ?? 0) + 1;
    }

    /**
     * @return array<string, int>
     */
    public static function getCounts(): array
    {
        return self::$counts;
    }
}
