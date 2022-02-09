<?php declare( strict_types=1 );

namespace PHP_SF\System\Core;

use PHP_SF\System\Classes\Abstracts\AbstractEventsDispatcher;


final class MiddlewareEventDispatcher extends AbstractEventsDispatcher
{
    protected static array $eventListenersList = [];

}