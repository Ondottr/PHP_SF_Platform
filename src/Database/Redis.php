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
        self::$rp = ( self::$rc = new Client )->pipeline();

        self::$rc
            ->select(
                match ($_ENV['APP_ENV']) {
                    'dev' => 2,
                    'test' => 1,
                    'prod' => 0
                }
            );
    }

    public static function getRc(): Client
    {
        if (!isset(self::$rc))
            new self;

        return self::$rc;
    }

    public static function getRp(): Pipeline
    {
        if (!isset(self::$rc))
            new self;

        return self::$rp;
    }
}
