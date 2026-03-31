<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Exception;

final class RouteParameterExpectedException extends RouteParameterException
{
    public function __construct( string $routeName, string $parameter )
    {
        parent::__construct(
            sprintf(
                'Route parameter `%s` expected for the "%s" route',
                $parameter, $routeName
            )
        );
    }
}
