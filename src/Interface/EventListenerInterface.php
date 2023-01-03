<?php declare( strict_types=1 );

namespace PHP_SF\System\Interface;

interface EventListenerInterface
{

    /**
     * @return array<string>
     */
    public function getListeners(): array;

}