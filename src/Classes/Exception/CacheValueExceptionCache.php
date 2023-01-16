<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Exception;

use Throwable;

class CacheValueExceptionCache extends InvalidCacheArgumentException
{

    public function __construct( string $message = '', int $code = 0, Throwable|null $previous = null )
    {
        if ( empty( $message ) )
            $message = 'The value must be a scalar!';

        parent::__construct( $message, $code, $previous );
    }

}