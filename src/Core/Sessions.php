<?php declare( strict_types=1 );

namespace PHP_SF\System\Core;

use Symfony\Component\HttpFoundation\Session\Session;

final class Sessions
{

    private static Session $instance;


    private function __construct()
    {
    }


    public static function getInstance(): Session
    {
        if (!isset(self::$instance)) {
            self::setSessionsInstance();
        }

        return self::$instance;
    }

    private static function setSessionsInstance(): void
    {
        self::$instance = new Session;
    }
}
