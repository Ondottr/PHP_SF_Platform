<?php declare(strict_types=1);

namespace PHP_SF\System\Core;

use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\NoReturn;
use PHP_SF\System\Classes\Abstracts\AbstractView;
use PHP_SF\System\Kernel;
use PHP_SF\System\Router;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

use function function_exists;

final class Response extends \Symfony\Component\HttpFoundation\Response
{
    /**
     * @var list<string>
     */
    public static array $activeTemplates = [];


    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $dataFromController
     * @param string|null           $renderedContent    Output of a template engine (Twig, Blade, …);
     *                                                  used instead of a class-based view
     * @param string|null           $contentCssClass    CSS class of the wrapper div around rendered content,
     *                                                  mirroring the short-class-name wrapper of class views
     * @param bool                  $useLayout          Whether to wrap the response in the header/footer layout
     */
    public function __construct(
        #[ExpectedValues(valuesFromClass: parent::class)]
        public readonly int $status = 200,
        array $headers = [],
        private readonly ?AbstractView $view = null,
        private readonly array $dataFromController = [],
        private readonly ?string $renderedContent = null,
        private readonly ?string $contentCssClass = null,
        private readonly bool $useLayout = true,
    ) {
        parent::__construct(status: $status, headers: $headers);
    }


    /**
     * Renders the full page (header + view + footer) into a string and stores it
     * via {@see setContent()} so that Symfony's KernelBrowser can read it in tests.
     * Unlike {@see send()}, this method does NOT flush the output buffer or call exit().
     */
    public function captureContent(string $routeUrl): void
    {
        ob_start();

        try {
            $withLayout = $this->useLayout && !str_starts_with($routeUrl, '/api/');

            if ($withLayout) {
                (new (Kernel::getHeaderTemplateClassName())($this->dataFromController))->show();
            }

            if ($this->view instanceof AbstractView) {
                $array = explode('\\', $this->view::class);
                echo '<div class="' . array_pop($array) . '">';
                $this->view->show();
                echo '</div>';
            } elseif (null !== $this->renderedContent) {
                if ($withLayout) {
                    echo '<div class="' . $this->contentCssClass . '">' . $this->renderedContent . '</div>';
                } else {
                    echo $this->renderedContent;
                }
            }

            if ($withLayout) {
                (new (Kernel::getFooterTemplateClassName())($this->dataFromController))->show();
            }

            $this->setContent((string) ob_get_clean());
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     * @noinspection OffsetOperationsInspection
     * @noinspection MissingParentCallInspection
     */
    #[NoReturn]
    public function send(bool $flush = true): never
    {
        $withLayout = $this->useLayout && false === str_starts_with(Router::$currentRoute->url, '/api/');

        if ($withLayout) {
            $headerClassName = (
                TEMPLATES_CACHE_ENABLED
                ? TemplatesCache::getInstance()->getCachedTemplateClass(Kernel::getHeaderTemplateClassName())
                : false
            ) ?: Kernel::getHeaderTemplateClassName();

            (new $headerClassName($this->dataFromController))->show();
        }

        if ($this->view instanceof AbstractView) {
            $array = explode('\\', $this->view::class); ?>

            <div class="<?php echo array_pop($array); ?>">
                <?php $this->view->show(); ?>
            </div>

            <?php
        } elseif (null !== $this->renderedContent) {
            if ($withLayout) {
                echo '<div class="' . $this->contentCssClass . '">' . $this->renderedContent . '</div>';
            } else {
                echo $this->renderedContent;
            }
        }

        if ($withLayout) {
            $footerClassName = (
                TEMPLATES_CACHE_ENABLED
                ? TemplatesCache::getInstance()->getCachedTemplateClass(Kernel::getFooterTemplateClassName())
                : false
            ) ?: Kernel::getFooterTemplateClassName();

            (new $footerClassName($this->dataFromController))->show();
        }

        $this->sendHeaders();
        ob_end_flush();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }

        $ctx = PhpSfContext::current();
        if (null !== $ctx) {
            $kernel = $ctx->getKernel();
            $request = r();

            PhpSfEventDispatcher::dispatch(KernelEvents::FINISH_REQUEST, new FinishRequestEvent(
                $kernel,
                $request,
                HttpKernelInterface::MAIN_REQUEST,
            ));

            PhpSfEventDispatcher::dispatch(KernelEvents::TERMINATE, new TerminateEvent(
                $kernel,
                $request,
                $this,
            ));
        }

        exit;
    }
}
