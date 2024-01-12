<?php declare( strict_types=1 );

namespace PHP_SF\System\Core;

use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\NoReturn;
use PHP_SF\System\Classes\Abstracts\AbstractView;
use PHP_SF\System\Kernel;
use PHP_SF\System\Router;
use function function_exists;

final class Response extends \Symfony\Component\HttpFoundation\Response
{

    public static array $activeTemplates = [];

    public function __construct(
        #[ExpectedValues( valuesFromClass: parent::class )]
        readonly int                       $status = 200,
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
     * @noinspection MissingParentCallInspection
     */
    #[NoReturn] public function send(bool $flush = true): static
    {
        if ( str_starts_with( Router::$currentRoute->url, '/api/' ) === false ) {
            if ( TEMPLATES_CACHE_ENABLED ) {
                $result = TemplatesCache::getInstance()->getCachedTemplateClass( Kernel::getHeaderTemplateClassName() );
                if ( $result !== false ) {
                    eval( $result['fileContent'] );
                    $headerClassName = $result['className'];
                }
            }

            if ( isset( $headerClassName ) === false )
                $headerClassName = Kernel::getHeaderTemplateClassName();

            ( new $headerClassName( $this->dataFromController ) )->show();

        }

        if ( $this->view instanceof AbstractView ) {
            $array = explode( '\\', $this->view::class ) ?>

            <div class="<?= array_pop( $array ) ?>">
                <?php $this->view->show() ?>
            </div>

            <?php
        }

        if ( str_starts_with( Router::$currentRoute->url, '/api/' ) === false ) {
            if ( TEMPLATES_CACHE_ENABLED ) {
                $result = TemplatesCache::getInstance()->getCachedTemplateClass( Kernel::getFooterTemplateClassName() );
                if ( $result !== false ) {
                    eval( $result['fileContent'] );
                    $footerClassName = $result['className'];
                }
            }

            if ( isset( $footerClassName ) === false )
                $footerClassName = Kernel::getFooterTemplateClassName();

            ( new $footerClassName( $this->dataFromController ) )->show();
        }

        ob_end_flush();

        if ( function_exists( 'fastcgi_finish_request' ) )
            fastcgi_finish_request();
        if ( function_exists( 'litespeed_finish_request' ) )
            /** @noinspection PhpUndefinedFunctionInspection */
            litespeed_finish_request();

        exit( die );

        /** @noinspection PhpUnreachableStatementInspection */
        return $this;
    }

}
