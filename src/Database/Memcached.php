<?php declare( strict_types=1 );

namespace PHP_SF\System\Database;

final class Memcached
{

    private static \Memcached $instance;

    private function __construct()
    {
        $prefix = sprintf( '%s:%s:', env( 'SERVER_PREFIX' ), env( 'APP_ENV' ) );

        self::$instance = new \Memcached( $prefix );

        self::$instance->addServer( env( 'MEMCACHED_SERVER' ), (int)env( 'MEMCACHED_PORT' ) );
    }

    public static function getInstance(): \Memcached
    {
        if ( !isset( self::$instance ) )
            new self;

        return self::$instance;
    }

}
