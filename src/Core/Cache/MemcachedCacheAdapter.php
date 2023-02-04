<?php declare( strict_types=1 );
/**
 * Created by PhpStorm.
 * User: ondottr
 * Date: 04/02/2023
 * Time: 8:55 am
 */

namespace PHP_SF\System\Core\Cache;

use DateInterval;
use PHP_SF\System\Classes\Abstracts\AbstractCacheAdapter;
use PHP_SF\System\Classes\Exception\CacheValueException;
use PHP_SF\System\Classes\Exception\InvalidCacheArgumentException;
use PHP_SF\System\Classes\Exception\UnsupportedPlatformException;
use PHP_SF\System\Database\Memcached;
use Throwable;

/**
 * The MemcachedCacheAdapter class is an implementation of PSR-16 Cache interface using Memcached as cache storage.
 * This class provides a simple and efficient way to store cache data in Memcached and retrieve it when needed.
 *
 * Use this class by calling the {@link mca()} function which returns an instance of this class.
 *
 * @note Use this class only if you have Redis installed and configured on your system.
 */
final class MemcachedCacheAdapter extends AbstractCacheAdapter
{

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default {@var $default} in case of cache miss.
     */
    public function get( string $key, mixed $default = null ): mixed
    {
        $result = Memcached::getInstance()->get( $key );

        if ( $result === false )
            return $default;

        return $result;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     * If the key already exists in the cache, it will be overwritten.
     * The value to be stored must be a scalar type, otherwise a {@link CacheValueException} will be thrown.
     * The time to live for the key can be specified in seconds, or as a {@link DateInterval} object.
     * If a {@link DateInterval} object is provided, it will be converted to seconds.
     *
     * @param string                $key   The key to store the value under.
     * @param mixed                 $value The value to store.
     * @param DateInterval|int|null $ttl   The time to live for the key in seconds.
     *                                     If not provided, the default value will be used {@link self::DEFAULT_TTL}.
     *
     * @throws CacheValueException If the value to be stored is not scalar.
     *
     * @throws InvalidCacheArgumentException If there is an issue setting the value in cache, such as
     *                                       a failure to connect to the cache store or invalid parameters.
     *
     * @return bool Whether the value was successfully stored.
     *              Returns true if the key was successfully set, false otherwise.
     */
    public function set( string $key, mixed $value, DateInterval|int|null $ttl = self::DEFAULT_TTL ): bool
    {
        if ( is_scalar( $value ) === false )
            throw new CacheValueException;

        if ( $ttl instanceof DateInterval )
            $ttl = $ttl->s + $ttl->i * 60 + $ttl->h * 3600 + $ttl->days * 86400;

        try {
            Memcached::getInstance()->set( $key, $value, $ttl );
        } catch ( Throwable $e ) {
            throw new InvalidCacheArgumentException( $e->getMessage(), $e->getCode(), $e );
        }

        return $this->has( $key );
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     */
    public function delete( string $key ): bool
    {
        return Memcached::getInstance()->delete( $key );
    }

    /**
     * Memcached does not support deleting keys by pattern because memcache doesn't guarantee to return all keys you
     * also cannot assume that all keys have been returned. <p>
     * So, there is no way to get all keys or keys by pattern from memcached and delete them.
     *
     * @link https://www.php.net/manual/en/memcached.getallkeys.php
     *
     * @throws UnsupportedPlatformException Always thrown because Memcached does not support deleting keys by pattern.
     */
    public function deleteByKeyPattern( string $keyPattern ): bool
    {
        throw new UnsupportedPlatformException(
            'Memcached does not support deleting keys by pattern. Use the "clear" method instead.'
        );
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear(): bool
    {
        return Memcached::getInstance()->flush();
    }

    /**
     * Determines if a cache item with the given key exists.
     *
     * @param string $key The key to check.
     * @return bool True if the key exists, False otherwise.
     */
    public function has( string $key ): bool
    {
        return Memcached::getInstance()->get( $key ) !== false;
    }

}