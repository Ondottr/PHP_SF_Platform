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

use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter\Standard;

final class PrettyPrinter extends Standard
{
    /**
     * Overridden to fix indentation problem with tabs.
     *
     * If the original source code uses tabs, then the tokenizer
     * will see this as "1" indent level, and will indent new lines
     * with just 1 space. By changing 1 indent to 4, we effectively
     * "correct" this problem when printing.
     *
     * For code that is even further indented (e.g. 8 spaces),
     * the printer uses the first indentation (here corrected
     * from 1 space to 4) and already (without needing any other
     * changes) adds 4 spaces onto that. This is why we don't
     * also need to handle indent levels of 5, 9, etc: these
     * do not occur (at least in the code we generate);
     */
    protected function setIndentLevel( int $level ): void
    {
        if ( $level === 1 )
            $level = 4;

        parent::setIndentLevel( $level );
    }

    /**
     * Overridden to change coding standards.
     *
     * Before:
     *      public function getFoo() : string
     *
     * After
     *      public function getFoo(): string
     */
    protected function pStmt_ClassMethod( Stmt\ClassMethod $node )
    {
        $classMethod = parent::pStmt_ClassMethod( $node );

        if ( $node->returnType ) {
            $classMethod = str_replace( ') :', '):', $classMethod );
        }

        return $classMethod;
    }
}
