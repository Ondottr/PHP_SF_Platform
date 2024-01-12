<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Exception;

use ErrorException;
use Throwable;

class ViewException extends ErrorException
{

    public function __construct(
        string         $message = "",
        int            $code = 0,
        int            $severity = 1,
        string|null    $filename = __FILE__,
        int|null       $line = __LINE__,
        Throwable|null $previous = null
    )
    {
        parent::__construct(
            sprintf( "There was an error while rendering the view: %s", $message ),
            $code, $severity, $filename, $line, $previous
        );
    }

}