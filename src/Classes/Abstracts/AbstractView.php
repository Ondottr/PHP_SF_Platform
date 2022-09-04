<?php declare(strict_types=1);
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

use JetBrains\PhpStorm\Pure;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Core\TemplatesCache;
use PHP_SF\System\Kernel;
use PHP_SF\Templates\Layout\footer;
use PHP_SF\Templates\Layout\Header\head;

use function array_key_exists;
use function is_array;

abstract class AbstractView
{

    public function __construct(
        protected array $data = []
    )
    {
        Response::$activeTemplates[] = static::class;
    }

    final public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    #[Pure] final public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    final public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        trigger_error("Undefined Property `$name` in view: " . static::class, E_USER_ERROR);
    }

    final public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * @noinspection OffsetOperationsInspection
     */
    final protected function import( string $className, array $data = [] ): void
    {
        if ( TEMPLATES_CACHE_ENABLED &&
            is_array( $arr = TemplatesCache::getInstance()->getCachedTemplateClass( $className ) )
        ) {
            require_once( $arr['fileName'] );
            $className = $arr['className'];
        }


        $class = new $className( [ ...$this->getData(), ...$data ] );

        if ( $class instanceof self ) {
            if (
                $class instanceof head || $class instanceof footer ||
                Kernel::isAutoTemplateClassesEnables() === false
            ) {
                $class->show();

                return;
            }

            $array = explode( '\\', $className );
            echo sprintf( '<div class="%s">', end( $array ) );
            $class->show();
            echo '</div>';
        }
    }

    final protected function getData(): array
    {
        return $this->data;
    }

    abstract public function show(): void;
}
