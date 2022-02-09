<?php /** @noinspection PhpAttributeCanBeAddedToOverriddenMemberInspection */
declare( strict_types=1 );

namespace PHP_SF\System\Interface;

use ReflectionMethod;


interface EventListenerInterface
{

    /**
     * @return array<ReflectionMethod>
     */
    public function getListeners(): array;

}