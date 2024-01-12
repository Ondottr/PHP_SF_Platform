<?php declare( strict_types=1 );
/**
 *  Copyright © 2018-2024, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 *  Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 *  granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 *  THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 *  INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 *  LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 *  RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 *  TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

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