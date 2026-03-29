<?php declare( strict_types=1 );

namespace PHP_SF\System\Core;

use DateTimeZone;
use RuntimeException;

/**
 * Representation of date and time with automatic project timezone {@see \DEFAULT_TIMEZONE}.
 * @link https://php.net/manual/en/class.datetime.php
 */
class DateTime extends \DateTime
{

    public function __construct(
        string            $datetime = 'now',
        DateTimeZone|null $timezone = new DateTimeZone( DEFAULT_TIMEZONE['name'] )
    )
    {
        parent::__construct( $datetime, $timezone );
    }

    final public function db_date(): string
    {
        return $this->format( 'Y-m-d' );
    }

    final public function db_datetime(): string
    {
        return $this->format( 'Y-m-d H:i:s' );
    }

    final public function db_time(): string
    {
        return $this->format( 'H:i:s' );
    }


    final public static function now(): self
    {
        return new self();
    }


    public static function createFromFormat( string $format, string $datetime, DateTimeZone|null $timezone = null ): self
    {
        $date = parent::createFromFormat( $format, $datetime, $timezone );
        if ( $date === false )
            throw new RuntimeException( sprintf( 'Invalid date format "%s"', $format ) );

        return new self( $date->format( 'Y-m-d H:i:s' ) );
    }

}