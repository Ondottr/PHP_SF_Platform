<?php
declare( strict_types=1 );

namespace PHP_SF\System\Interface;

use PHP_SF\System\Classes\Abstracts\AbstractEventListener;


interface EventSubscriberInterface
{

    public function dispatchEvent(AbstractEventListener $eventListener, mixed $args): bool;

}