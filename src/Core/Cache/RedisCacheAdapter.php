<?php declare( strict_types=1 );

namespace PHP_SF\System\Core\Cache;

use DateInterval;
use PHP_SF\System\Classes\Abstracts\AbstractCacheAdapter;
use PHP_SF\System\Classes\Exception\CacheKeyExceptionCache;
use PHP_SF\System\Classes\Exception\CacheValueException;
use PHP_SF\System\Classes\Exception\InvalidCacheArgumentException;
use Throwable;

/**
 * The RedisCacheAdapter class is an implementation of PSR-16 Cache interface using Redis as cache storage.
 * This class provides a simple and efficient way to store cache data in Redis and retrieve it when needed.
 * <p>
 * Use this class by calling the {@link ra()} function which returns an instance of this class.
 *
 * @note Use this class only if you have Redis installed and configured on your system.
 */
final class RedisCacheAdapter extends AbstractCacheAdapter
{

    public const DEFAULT_TTL = 86400;

    /**
     * Instance of the class
     */
    private static self $instance;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {}


    /**
     * Get the instance of the class
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if ( isset( self::$instance ) === false )
            self::setInstance();

        return self::$instance;
    }

    /**
     * Set the instance of the class
     */
    private static function setInstance(): void
    {
        self::$instance = new self;
    }


    /**
     * Get the value of a key from the cache
     *
     * @param string $key The key to retrieve
     * @param mixed $default The default value to return if the key does not exist
     *
     * @return mixed
     */
    public function get( string $key, mixed $default = null ): mixed
    {
        return rc()->get( $key ) ?? $default;
    }

    /**
     * Store a value in the cache
     * This method is used to store a value in cache using a specified key.
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
                rc()->setex( $key, $ttl, $value );
            else
                rc()->set( $key, $value );
        } catch ( Throwable $e ) {
            throw new InvalidCacheArgumentException( $e->getMessage(), $e->getCode(), $e );
        }

        return $this->has( $key );
    }

    /**
     * Delete a key from the cache
     *
     * @param string $key The key to delete
     *
     * @return bool Whether the key was successfully deleted
     */
    public function delete( string $key ): bool
    {
        return rc()->del( $key ) > 0;
    }

    /**
     * Deletes cache keys that match a certain pattern.
     *
     * @param string $keyPattern The pattern that the keys need to match.
     * @return bool True if all the keys were successfully deleted, False otherwise.
     */
    public function deleteByKeyPattern( string $keyPattern ): bool
    {
        $keys = rc()->keys( $keyPattern );

        if ( empty( $keys ) )
            return false;

        $result = true;
        foreach ( $keys as $key )
            $result = $result && 0 < rc()->del( str_replace( sprintf( '%s:%s:', env( 'SERVER_PREFIX' ), env( 'APP_ENV' ) ), '', $key ) );

        return $result;
    }

    /**
     * Clears all the keys in the current Redis database.
     *
     * @return bool True if the database was successfully cleared, False otherwise.
     */
    public function clear(): bool
    {
        rc()->flushdb();

        return empty( rc()->keys( '*' ) );
    }

    /**
     * Gets multiple cache items by their keys.
     *
     * @param iterable $keys The keys to retrieve.
     * @param mixed $default The value to return if a key is not found.
     * @return iterable An array of key-value pairs.
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
     * Sets multiple cache items.
     *
     * @param iterable $values The values to set, in the form of key-value pairs.
     * @param DateInterval|int|null $ttl The time to live of the values, in seconds.
     * @return bool True if all the values were successfully set, False otherwise.
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
     * Deletes multiple cache items by their keys.
     *
     * @param iterable $keys The keys to delete.
     * @return bool True if all the keys were successfully deleted, False otherwise.
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
     * Determines if a cache item with the given key exists.
     *
     * @param string $key The key to check.
     * @return bool True if the key exists, False otherwise.
     */
    public function has( string $key ): bool
    {
        return rc()->get( $key ) !== null;
    }

}