<?php declare( strict_types=1 );

/**
 *  Copyright © 2018-2022, Nations Original Sp. z o.o. <contact@nations-original.com>
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

namespace PHP_SF\System\Classes\Exception;

use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

final class InvalidRouteMethodParameterTypeException extends InvalidTypeException
{

    public function __construct( string $type, string $propertyName, object $data )
    {
        parent::__construct(
            sprintf(
                'Invalid method parameter type in %s::%s for property “%s”, available types: "string|int|float" and `%s` provided!',
                $data->class,
                $data->method,
                $propertyName,
                $type
            )
        );
    }
}
