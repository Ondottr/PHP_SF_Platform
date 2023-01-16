<?php declare( strict_types=1 );

namespace PHP_SF\System\Core\Cache;

use DateInterval;
use PHP_SF\System\Classes\Abstracts\AbstractCacheAdapter;
use PHP_SF\System\Classes\Exception\CacheKeyExceptionCache;
use PHP_SF\System\Classes\Exception\CacheValueExceptionCache;
use PHP_SF\System\Classes\Exception\InvalidCacheArgumentException;
use Predis\Response\ServerException;

final class RedisCacheAdapter extends AbstractCacheAdapter
{

    private static self $instance;


    private function __construct() {}


    public static function getInstance(): self
    {
        if ( isset( self::$instance ) === false )
            self::setInstance();

        return self::$instance;
    }

    private static function setInstance(): void
    {
        self::$instance = new self;
    }


    public function get( string $key, mixed $default = null ): mixed
    {
        if ( $this->has( $key ) )
            return rc()->get( $key );

        return $default;
    }

    public function set( string $key, mixed $value, DateInterval|int|null $ttl = null ): bool
    {
        if ( is_scalar( $value ) === false )
            throw new CacheValueExceptionCache;

        if ( $ttl instanceof DateInterval )
            $ttl = $ttl->s + $ttl->i * 60 + $ttl->h * 3600 + $ttl->days * 86400;

        try {
            rc()->set( $key, $value, expireTTL: $ttl );
        } catch ( ServerException $e ) {
            throw new InvalidCacheArgumentException( $e->getMessage(), $e->getCode(), $e );
        }

        return $this->has( $key );
    }

    public function delete( string $key ): bool
    {
        rc()->del( $key );

        return $this->has( $key );
    }

    public function clear(): bool
    {
        rc()->flushdb();

        return empty( rc()->keys( '*' ) );
    }

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

    public function setMultiple( iterable $values, DateInterval|int|null $ttl = null ): bool
    {
        $result = true;

        foreach ( $values as $key => $value ) {
            if ( is_string( $key ) === false )
                throw new CacheKeyExceptionCache;

            if ( is_scalar( $value ) === false )
                throw new CacheValueExceptionCache;

            $result = $result && $this->set( $key, $value, $ttl );
        }

        return $result;
    }

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

    public function has( string $key ): bool
    {
        return rc()->get( $key ) !== null;
    }

}