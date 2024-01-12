<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Exception;

use Throwable;

final class CacheKeyExceptionCache extends InvalidCacheArgumentException
{

    public function __construct( string $message = '', int $code = 0, Throwable|null $previous = null )
    {
        if ( empty( $message ) )
            $message = 'Keys must be strings!';

        parent::__construct( $message, $code, $previous );
    }

}