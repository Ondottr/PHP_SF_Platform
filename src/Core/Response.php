<?php declare( strict_types=1 );

namespace PHP_SF\System\Core;

use JetBrains\PhpStorm\NoReturn;
use PHP_SF\System\Classes\Abstracts\AbstractView;
use PHP_SF\System\Kernel;
use PHP_SF\System\Router;

use function function_exists;
use function in_array;
use function is_array;

use const PHP_SAPI;

final class Response extends \Symfony\Component\HttpFoundation\Response
{

    public static array $activeTemplates = [];

    public function __construct(
        int                                $status = 200,
        array                              $headers = [],
        private readonly AbstractView|null $view = null,
        private readonly array             $dataFromController = []
    )
    {
        parent::__construct( status: $status, headers: $headers );
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     * @noinspection OffsetOperationsInspection
     */
    #[NoReturn] public function send(): static
    {
        if ( str_starts_with( Router::$currentRoute->url, '/api/' ) === false ) {
            if ( TEMPLATES_CACHE_ENABLED &&
                is_array( $arr = TemplatesCache::getInstance()->getCachedTemplateClass( Kernel::getHeaderTemplateClassName() ) )
            ) {
                require_once( $arr['fileName'] );
                $viewClassName = $arr['className'];

                $header = new $viewClassName( $this->getDataFromController() );
                $header->show();
            } else {
                $headerClassName = Kernel::getHeaderTemplateClassName();

                $header = new $headerClassName( $this->getDataFromController() );
                $header->show();
            }

            echo '<div class="content">';
        }

        if ( $this->view instanceof AbstractView ) {
            $array = explode( '\\', $this->view::class ) ?>

            <div class="<?= end( $array ) ?>">
                <?php $this->view->show() ?>
            </div>

            <?php
        }

        if ( str_starts_with( Router::$currentRoute->url, '/api/' ) === false ) {
            if ( TEMPLATES_CACHE_ENABLED &&
                is_array( $arr = TemplatesCache::getInstance()->getCachedTemplateClass( Kernel::getFooterTemplateClassName() ) )
            ) {
                /** @noinspection OffsetOperationsInspection */
                require_once( $arr['fileName'] );
                /** @noinspection OffsetOperationsInspection */
                $viewClassName = $arr['className'];

                $footer = new $viewClassName( $this->getDataFromController() );
                $footer->show();
            } else {
                $footerClassName = Kernel::getFooterTemplateClassName();

                $footer = new $footerClassName( $this->getDataFromController() );
                $footer->show();
            }

            echo '</div>';
        }

        if ( function_exists( 'fastcgi_finish_request' ) )
            fastcgi_finish_request();
        elseif ( function_exists( 'litespeed_finish_request' ) )
            litespeed_finish_request();
        elseif ( !in_array( PHP_SAPI, [ 'cli', 'phpdbg' ], true ) )
            self::closeOutputBuffers( 0, true );

        return $this;
    }

    public function getDataFromController(): array
    {
        return $this->dataFromController;
    }

}
