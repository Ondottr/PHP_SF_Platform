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

use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

final class MakerFileLinkFormatter
{
    private ?FileLinkFormatter $fileLinkFormatter;

    public function __construct( FileLinkFormatter $fileLinkFormatter = null )
    {
        $this->fileLinkFormatter = $fileLinkFormatter;
    }

    public function makeLinkedPath( string $absolutePath, string $relativePath ): string
    {
        if ( !$this->fileLinkFormatter )
            return $relativePath;

        if ( !$formatted = $this->fileLinkFormatter->format( $absolutePath, 1 ) )
            return $relativePath;


        if ( getenv( 'MAKER_DISABLE_FILE_LINKS' ) )
            return $relativePath;


        $outputFormatterStyle = new OutputFormatterStyle();

        if ( method_exists( OutputFormatterStyle::class, 'setHref' ) )
            $outputFormatterStyle->setHref( $formatted );


        return $outputFormatterStyle->apply( $relativePath );
    }
}
