<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use PHP_SF\System\Interface\EventSubscriberInterface;
use ReflectionClass;
use ReflectionMethod;


abstract class AbstractEventSubscriber implements EventSubscriberInterface
{

    final public function __construct() {}


    final protected function event(string $name): ReflectionMethod
    {
        return ( new ReflectionClass(static::class) )
            ->getMethod($name);
    }

}