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

namespace PHP_SF\System\Classes\Abstracts;

use PHP_SF\System\Core\Response;
use PHP_SF\System\Core\TemplatesCache;
use PHP_SF\Templates\Layout\footer;
use PHP_SF\Templates\Layout\Header\head;
use function array_key_exists;

abstract class AbstractView
{

    public function __construct(
        protected readonly array $data = [],
        protected readonly bool  $viewClassTagEnabled = true,
    )
    {
        Response::$activeTemplates[] = static::class;
    }


    final public function isViewClassTagEnabled(): bool
    {
        return $this->viewClassTagEnabled;
    }


    final protected function import( string $className, array $data = [], bool $htmlClassTagEnabled = true ): void
    {
        if ( TEMPLATES_CACHE_ENABLED ) {
            $arr = TemplatesCache::getInstance()->getCachedTemplateClass( $className );
            if ( $arr !== false ) {
                require_once( $arr['fileName'] );
                $className = $arr['className'];
            }
        }


        $class = new $className( array_merge( $this->data, $data ), $htmlClassTagEnabled );

        if ( $class instanceof self ) {
            if ( $class instanceof head || $class instanceof footer )
                $class->show();

            else {
                $array = explode( '\\', $className );
                if ( $class->isViewClassTagEnabled() )
                    echo sprintf( '<div class="%s">', array_pop( $array ) );

                $class->show();

                if ( $class->isViewClassTagEnabled() )
                    echo '</div>';

            }
        }
    }

    abstract public function show(): void;


    /**
     * @noinspection MagicMethodsValidityInspection
     */
    final public function __get( string $name ): mixed
    {
        if ( isset( $this->data ) )
            return $this->data[ $name ];

        trigger_error( "Undefined Property `$name` in view: " . static::class, E_USER_ERROR );
    }

    /**
     * @noinspection MagicMethodsValidityInspection
     */
    public function __isset( string $name ): bool
    {
        return array_key_exists( $name, $this->data );
    }

}
