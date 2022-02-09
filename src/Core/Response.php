<?php
declare( strict_types=1 );

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
        int                   $status = 200,
        array                 $headers = [],
        private ?AbstractView $view = null
    )
    {
        parent::__construct(status: $status, headers: $headers);
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     * @noinspection OffsetOperationsInspection
     */
    #[NoReturn] public function send(): never
    {
        if (str_starts_with(Router::$currentRoute->url, '/api/') === false) {
            if (TEMPLATES_CACHE_ENABLED &&
                is_array($arr = tc()->getCachedTemplateClass(Kernel::getHeaderTemplateClassName()))
            ) {
                require_once( $arr[ 'fileName' ] );
                $view = $arr[ 'className' ];

                ( new $view )->show();
            } else {
                ( new ( Kernel::getHeaderTemplateClassName() ) )->show();
            }

            echo '<div class="content">';
        }

        if ($this->view instanceof AbstractView) {
            $array = explode('\\', $this->view::class);

            echo sprintf('<div class="%s">', end($array));
            $this->view->show();
            echo '</div>';
        }

        if (str_starts_with(Router::$currentRoute->url, '/api/') === false) {
            if (TEMPLATES_CACHE_ENABLED &&
                is_array($arr = tc()->getCachedTemplateClass(Kernel::getFooterTemplateClassName()))
            ) {
                /** @noinspection OffsetOperationsInspection */
                require_once( $arr[ 'fileName' ] );
                /** @noinspection OffsetOperationsInspection */
                $view = $arr[ 'className' ];

                ( new $view )->show();
            } else {
                ( new ( Kernel::getFooterTemplateClassName() ) )->show();
            }

            echo '</div>';
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } elseif (!in_array(PHP_SAPI, [ 'cli', 'phpdbg' ], true)) {
            self::closeOutputBuffers(0, true);
        }

        die();
    }

}
