<?php declare( strict_types=1 );

namespace PHP_SF\System\Core\Cache;

use DateInterval;
use PHP_SF\System\Classes\Abstracts\AbstractCacheAdapter;
use PHP_SF\System\Classes\Exception\CacheKeyExceptionCache;
use PHP_SF\System\Classes\Exception\CacheValueException;
use PHP_SF\System\Classes\Exception\InvalidCacheArgumentException;
use PHP_SF\System\Database\Redis;
use Throwable;

/**
 * The RedisCacheAdapter class is an implementation of PSR-16 Cache interface using Redis as cache storage.
 * This class provides a simple and efficient way to store cache data in Redis and retrieve it when needed.
 *
 * Use this class by calling the {@link rca()} function which returns an instance of this class.
 *
 * @note Use this class only if you have Redis installed and configured on your system.
 */
final class RedisCacheAdapter extends AbstractCacheAdapter
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
        return Redis::getClient()->get( $key ) ?? $default;
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
            if ( $ttl !== null )
                Redis::getClient()->setex( $key, $ttl, $value );
            else
                Redis::getClient()->set( $key, $value );
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
        return Redis::getClient()->del( $key ) > 0;
    }

    /**
     * Deletes cache keys that match a certain pattern.
     *
     * @param string $keyPattern The pattern that the keys need to match.
     *                           Only alphanumeric characters and "*" are allowed.
     *
     * @throws CacheKeyExceptionCache If the key pattern is not valid.
     *                                The "*" character must be at the beginning or at the end of the pattern, not in the middle.
     *                                Example: "my_key_*" or "*_my_key" or "*my_key*" are valid patterns.
     *                                Example: "my_*_key" is not a valid pattern.
     *
     * @return bool True if all the keys were successfully deleted, False otherwise.
     */
    public function deleteByKeyPattern( string $keyPattern ): bool
    {
        if ( preg_match('/^[a-zA-Z0-9*]+$/', $keyPattern) === false )
            throw new CacheKeyExceptionCache(
                sprintf( 'The key pattern "%s" is not valid. Only alphanumeric characters and "*" are allowed.', $keyPattern )
            );

        if ( preg_match( "/^[^*].*[^*]$/", $keyPattern ) )
            throw new CacheKeyExceptionCache(
                sprintf( 'The key pattern "%s" is not valid. The "*" character must be at the beginning or at the end of the pattern, not in the middle.', $keyPattern )
            );

        $keys = Redis::getClient()->keys( $keyPattern );

        if ( empty( $keys ) )
            return false;

        $result = true;
        foreach ( $keys as $key )
            $result = $result && $this->delete( str_replace( sprintf( '%s:%s:', env( 'SERVER_PREFIX' ), env( 'APP_ENV' ) ), '', $key ) );

        return $result;
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear(): bool
    {
        return Redis::getClient()->flushdb()->getPayload() === 'OK';
    }

    /**
     * Determines if a cache item with the given key exists.
     *
     * @param string $key The key to check.
     * @return bool True if the key exists, False otherwise.
     */
    public function has( string $key ): bool
    {
        return Redis::getClient()->get( $key ) !== null;
    }

}