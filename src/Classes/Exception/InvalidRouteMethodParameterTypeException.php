<?php

declare( strict_types=1 );

namespace PHP_SF\System\Classes\Exception;

use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

class InvalidRouteMethodParameterTypeException extends InvalidTypeException
{

    public function __construct(string $type, string $propertyName, object $data)
    {
        parent::__construct(
            _t(
                'invalid_route_method_parameter_type_exception',
                $data->class,
                $data->method,
                $propertyName,
                $type
            )
        );
    }
}
