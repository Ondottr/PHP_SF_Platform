<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Exception;

final class InvalidRouteMethodParameterTypeException extends RouteParameterException
{
    public function __construct(string $type, string $propertyName, object $data)
    {
        parent::__construct(
            sprintf(
                'Invalid method parameter type in %s::%s for property “%s”, available types: “string|int|float” or any AbstractEntity subclass, `%s` provided!',
                $data->class,
                $data->method,
                $propertyName,
                $type,
            ),
        );
    }
}
