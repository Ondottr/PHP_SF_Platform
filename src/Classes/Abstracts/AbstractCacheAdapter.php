<?php /** @noinspection MethodShouldBeFinalInspection */
declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use DateInterval;
use PHP_SF\System\Classes\Exception\CacheKeyExceptionCache;
use PHP_SF\System\Classes\Exception\CacheValueException;
use PHP_SF\System\Classes\Exception\InvalidCacheArgumentException;
use PHP_SF\System\Core\Cache\APCuCacheAdapter;
use PHP_SF\System\Core\Cache\MemcachedCacheAdapter;
use PHP_SF\System\Core\Cache\RedisCacheAdapter;
use PHP_SF\System\Interface\CacheInterface;

abstract class AbstractCacheAdapter implements CacheInterface
{

    public const APCU_CACHE_ADAPTER  = APCuCacheAdapter::class;
    public const REDIS_CACHE_ADAPTER = RedisCacheAdapter::class;
    public const MEMCACHED_CACHE_ADAPTER = MemcachedCacheAdapter::class;

    protected const DEFAULT_TTL = 86400;

    /**
     * Array of Cache Adapter instances
     *
     * @var static[]
     */
    private static array $instances = [];


    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {}


    /**
     * Get the instance of the class
     *
     * @return static
     */
    public static function getInstance(): static
    {
        if ( array_key_exists( static::class, self::$instances ) === false )
            self::setInstance();

        return self::$instances[ static::class ];
    }

    /**
     * Set the instance of the class
     *
     */
    private static function setInstance(): void
    {
        self::$instances[ static::class ] = match ( static::class ) {
            self::APCU_CACHE_ADAPTER      => new ApcuCacheAdapter,
            self::REDIS_CACHE_ADAPTER     => new RedisCacheAdapter,
            self::MEMCACHED_CACHE_ADAPTER => new MemcachedCacheAdapter,
            default                       => throw new InvalidCacheArgumentException( 'Invalid cache adapter type' ),
        };
    }


    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable<string> $keys    A list of keys that can be obtained in a single operation.
     * @param mixed            $default Default value to return for keys that do not exist.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException If a key is not a string
     *
     * @return iterable<string, mixed> A list of key => value pairs.
     *                                 Cache keys that do not exist or are stale will have $default as value.
     */
    public function getMultiple( iterable $keys, mixed $default = null ): iterable
    {
        $result = [];

        foreach ( $keys as $key ) {
            if ( is_string( $key ) === false )
                throw new CacheKeyExceptionCache;

            $result[ $key ] = $this->get( $key, $default );
        }

        return $result;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable              $values  A list of key => value pairs for a multiple-set operation.
     * @param null|int|DateInterval $ttl     Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if any of the $values are not a legal value.
     *
     * @return bool True on success and false on failure.
     */
    public function setMultiple( iterable $values, DateInterval|int|null $ttl = self::DEFAULT_TTL ): bool
    {
        $result = true;

        foreach ( $values as $key => $value ) {
            if ( is_string( $key ) === false )
                throw new CacheKeyExceptionCache;

            if ( is_scalar( $value ) === false )
                throw new CacheValueException;

            $result = $result && $this->set( $key, $value, $ttl );
        }

        return $result;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable<string> $keys A list of string-based keys to be deleted.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if any of the $keys are not a legal value.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     */
    public function deleteMultiple( iterable $keys ): bool
    {
        $result = true;

        foreach ( $keys as $key ) {
            if ( is_string( $key ) === false )
                throw new CacheKeyExceptionCache;

            $result = $result && $this->delete( $key );
        }

        return $result;
    }


    /**
     * Private clone method to prevent cloning of singleton instance
     */
    private function __clone(): void {}

}