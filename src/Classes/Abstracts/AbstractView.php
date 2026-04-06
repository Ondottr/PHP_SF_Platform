<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use InvalidArgumentException;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Core\TemplatesCache;
use PHP_SF\Templates\Layout\footer;
use PHP_SF\Templates\Layout\HeaderComponents\head;

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


    final protected function import( string $view, array $data = [], bool $htmlClassTagEnabled = true ): void
    {
        if ( TEMPLATES_CACHE_ENABLED )
            $view = TemplatesCache::getInstance()->getCachedTemplateClass( $view ) ?: $view;

        $class = new $view( [ ...$this->data, ...$data ], $htmlClassTagEnabled );

        if ( $class instanceof self ) {
            if ( $class instanceof head || $class instanceof footer )
                $class->show();

            else {
                $array = explode( '\\', $view );
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
        if ( array_key_exists( $name, $this->data ) )
            return $this->data[ $name ];

        throw new InvalidArgumentException( "Undefined Property `$name` in view: " . static::class );
    }

    /**
     * @noinspection MagicMethodsValidityInspection
     */
    public function __isset( string $name ): bool
    {
        return array_key_exists( $name, $this->data );
    }

}
