<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Exception;

use Throwable;

class ViewException extends FrameworkException
{

    public function __construct(
        string         $message = '',
        int            $code = 0,
        Throwable|null $previous = null
    )
    {
        parent::__construct(
            sprintf( 'There was an error while rendering the view: %s', $message ),
            $code, $previous
        );
    }

}
