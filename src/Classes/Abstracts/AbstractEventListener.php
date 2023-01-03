<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use PHP_SF\System\Interface\EventListenerInterface;
use Symfony\Polyfill\Intl\Icu\Exception\NotImplementedException;


abstract class AbstractEventListener implements EventListenerInterface
{

    /**
     * TODO:: test EventListeners
     */
    final public function __construct()
    {
        throw new NotImplementedException('This class cannot be instantiated!');
    }


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