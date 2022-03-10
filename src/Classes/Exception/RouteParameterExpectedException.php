<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Exception;

use Exception;

class RouteParameterExpectedException extends Exception
{
    public function __construct( string $routeName, string $parameter)
    {
        parent::__construct(
            _t('route_parameters_expected',
               $parameter, $routeName
            )
        );
    }
}
