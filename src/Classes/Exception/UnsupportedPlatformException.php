<?php declare( strict_types=1 );
/**
 * Created by PhpStorm.
 * User: ondottr
 * Date: 04/02/2023
 * Time: 11:51 am
 */

namespace PHP_SF\System\Classes\Exception;

use Throwable;

final class UnsupportedPlatformException extends InvalidCacheArgumentException
{
    public function __construct( string $message = '', int $code = 0, Throwable $previous = null )
    {
        parent::__construct( $message, $code, $previous );
    }
}