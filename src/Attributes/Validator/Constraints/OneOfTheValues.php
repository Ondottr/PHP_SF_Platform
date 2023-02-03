<?php declare( strict_types=1 );
/*
 * Copyright © 2018-2023, Nations Original Sp. z o.o. <contact@nations-original.com>
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

namespace PHP_SF\System\Attributes\Validator\Constraints;

use Attribute;
use PHP_SF\System\Classes\Abstracts\AbstractConstraint;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

use function is_int;
use function is_string;

#[Attribute( Attribute::TARGET_PROPERTY )]
class OneOfTheValues extends AbstractConstraint
{
    public function __construct( public array $arr )
    {
        foreach ( $arr as $value )
            if ( is_string( $value ) === false && is_int( $value ) === false )
                throw new UnexpectedValueException( $value, 'string|integer' );

    }
}
