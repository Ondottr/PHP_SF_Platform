<?php declare( strict_types=1 );

/*
 * Copyright © 2018-2022, Nations Original Sp. z o.o. <contact@nations-original.com>
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

namespace Symfony\Bundle\MakerBundle\Util;

use Symfony\Bundle\MakerBundle\Str;

final class ClassNameValue
{
    private string $typeHint;
    private string $fullClassName;

    public function __construct( string $typeHint, string $fullClassName )
    {
        $this->typeHint      = $typeHint;
        $this->fullClassName = $fullClassName;
    }

    public function __toString()
    {
        return $this->getShortName();
    }

    public function getShortName(): string
    {
        if ( $this->isSelf() )
            return Str::getShortClassName( $this->fullClassName );

        return $this->typeHint;
    }

    public function isSelf(): bool
    {
        return $this->typeHint === 'self';
    }
}
