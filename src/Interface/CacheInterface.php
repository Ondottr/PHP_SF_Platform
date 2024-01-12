<?php declare( strict_types=1 );
/**
 * Created by PhpStorm.
 * User: ondottr
 * Date: 21/01/2023
 * Time: 12:52 PM
 */

namespace PHP_SF\System\Interface;

use DateInterval;

interface CacheInterface
{

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default {@var $default} in case of cache miss.
     */
    public function get( string $key, mixed $default = null ): mixed;

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                 $key   The key of the item to store.
     * @param mixed                  $value The value of the item to store, must be serializable.
     * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     * @return bool True on success and false on failure.
     *
     */
    public function set( string $key, mixed $value, int|DateInterval|null $ttl = null ): bool;

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     */
    public function delete( string $key ): bool;

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear(): bool;

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
    public function getMultiple( iterable $keys, mixed $default = null ): iterable;

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable              $values  A list of key => value pairs for a multiple-set operation.
     * @param null|int|DateInterval $ttl     Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if any of the $values are not a legal value.
     * @return bool True on success and false on failure.
     *
     */
    public function setMultiple( iterable $values, int|DateInterval|null $ttl = null ): bool;

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable<string> $keys A list of string-based keys to be deleted.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     */
    public function deleteMultiple( iterable $keys ): bool;

    /**
     * Determines if a cache item with the given key exists.
     *
     * @param string $key The key to check.
     *
     * @return bool True if the key exists, False otherwise.
     */
    public function has( string $key ): bool;

}
