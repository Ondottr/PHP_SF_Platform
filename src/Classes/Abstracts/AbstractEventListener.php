<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use PHP_SF\System\Interface\EventListenerInterface;


abstract class AbstractEventListener implements EventListenerInterface
{

    protected static bool $isExecuted = false;

    final public static function markExecuted(): void
    {
        static::$isExecuted = true;
    }

    final public static function isExecuted(): bool
    {
        return static::$isExecuted;
    }

}