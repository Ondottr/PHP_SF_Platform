<?php declare( strict_types=1 );

namespace PHP_SF\System\Database;

use Predis\Client;
use Predis\Pipeline\Pipeline;

final class Redis
{

    private static Client   $client;
    private static Pipeline $pipeline;

    private function __construct()
    {
        $redisCacheUrl = env( 'REDIS_CACHE_URL', 'redis://localhost:6379/0' );

        $redisHost = parse_url( $redisCacheUrl, PHP_URL_HOST );
        $redisPort = parse_url( $redisCacheUrl, PHP_URL_PORT );
        $redisDB   = (int)parse_url( $redisCacheUrl, PHP_URL_PATH );

        self::$client   = new Client(
            parameters: [ 'host' => $redisHost, 'port' => $redisPort ],
            options: [ 'prefix' => sprintf( '%s:%s:', env( 'SERVER_PREFIX' ), env( 'APP_ENV' ) ) ]
        );
        self::$pipeline = self::$client->pipeline();

        self::$client->select( $redisDB );
    }

    public static function getClient(): Client
    {
        if ( !isset( self::$client ) )
            new self;

        return self::$client;
    }

    public static function getPipeline(): Pipeline
    {
        if ( !isset( self::$client ) )
            new self;

        return self::$pipeline;
    }

}
