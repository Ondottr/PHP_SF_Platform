<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Exception;

class RouteParameterException extends \Exception
{
    public function __construct($message = '', $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
