<?php declare( strict_types=1 );
/*
 * Copyright © 2018-2024, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 * granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 * INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 * LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 * RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 * TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace PHP_SF\System\Classes\Abstracts;

/**
 * Class AbstractDataTransferObject
 *
 * @package PHP_SF\System\Classes\Abstracts
 * @author  Dmytro Dyvulskyi <dmytro.dyvulskyi@nations-original.com>
 */
abstract readonly class AbstractDataTransferObject
{

    /**
     * Create an instance of DTO class from array
     *
     * @param array $array
     *`
     * @return static
     */
    public static function fromArray( array $array ): static
    {
        return new static( ...$array );
    }

    public static function fromJSON( string $json ): static
    {
        return static::fromArray( j_decode( $json, true ) );
    }

    /**
     * Convert the DTO to an associative array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return get_object_vars( $this );
    }

    /**
     * Convert the DTO to string.
     *
     * @return object
     */
    public function toString(): string
    {
        return json_encode( $this->toArray() );
    }

}
