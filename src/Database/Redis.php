<?php declare( strict_types=1 );

namespace PHP_SF\System\Database;

use Predis\Client;
use Predis\Pipeline\Pipeline;

final class Redis
{

    private static Client   $rc;
    private static Pipeline $rp;

    private function __construct()
    {
        self::$rc = new Client( options: [ 'prefix' => sprintf( '%s:%s:', env( 'SERVER_PREFIX' ), env( 'APP_ENV' ) ) ] );
        self::$rp = self::$rc->pipeline();

        $arr = array_values( explode( '/', env( 'REDIS_CACHE_URL', 'redis://localhost:6379/0' ) ) );
        self::$rc->select( end( $arr ) );
    }

    public static function getRc(): Client
    {
        if ( !isset( self::$rc ) )
            new self;

        return self::$rc;
    }

    public static function getRp(): Pipeline
    {
        if ( !isset( self::$rc ) )
            new self;

        return self::$rp;
    }

}
