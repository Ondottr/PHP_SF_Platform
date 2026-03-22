<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Exception;

use Throwable;

final class UnsupportedPlatformException extends InvalidCacheArgumentException
{
    public function __construct( string $message = '', int $code = 0, Throwable $previous = null )
    {
        parent::__construct( $message, $code, $previous );
    }
}
