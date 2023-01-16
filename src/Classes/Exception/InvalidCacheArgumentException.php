<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Exception;

use Error;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

class InvalidCacheArgumentException extends Error implements InvalidArgumentException
{

    public function __construct( string $message = '', int $code = 0, Throwable|null $previous = null )
    {
        if ( empty( $message ) )
            $message = 'Cache has an invalid argument!';

        parent::__construct( $message, $code, $previous );
    }

}