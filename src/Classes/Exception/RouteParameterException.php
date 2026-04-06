<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Exception;

use Throwable;

class RouteParameterException extends FrameworkException
{

    public function __construct( $message = "", $code = 0, Throwable|null $previous = null )
    {
        parent::__construct( $message, $code, $previous );
    }

}