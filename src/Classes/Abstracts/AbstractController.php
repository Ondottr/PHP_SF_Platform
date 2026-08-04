<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Abstracts;

use PHP_SF\System\Core\Response;
use PHP_SF\System\Core\TemplateEngineRegistry;
use PHP_SF\System\Core\TemplatesCache;
use PHP_SF\System\Traits\JsonResponseHelperTrait;
use PHP_SF\System\Traits\RedirectTrait;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

abstract class AbstractController
{
    use RedirectTrait;

    use JsonResponseHelperTrait;


    /**
     * Renders a view. The view is either a class name extending {@see AbstractView}
     * (classic plain-PHP class views) or a template file name dispatched to a
     * registered template engine by extension, e.g. `pages/home.html.twig` (Twig)
     * or `pages/home.blade.php` (Blade).
     *
     * @param array<string, mixed> $data
     * @param bool                 $useLayout Whether to wrap the rendered view in the
     *                                        header/footer layout. Set to false for full-page templates that provide their
     *                                        own layout (e.g. Twig's `{% extends %}` or Blade's `@extends`).
     */
    final protected function render(string $view, array $data = [], ?string $pageTitle = null, bool $useLayout = true): Response
    {
        s()->set('page_title', $pageTitle ?? APPLICATION_NAME);

        if (null !== $engine = TemplateEngineRegistry::resolve($view)) {
            return new Response(
                dataFromController: $data,
                renderedContent: $engine->render($view, $data),
                contentCssClass: TemplateEngineRegistry::templateCssClass($view),
                useLayout: $useLayout,
            );
        }

        if (TEMPLATES_CACHE_ENABLED) {
            $view = TemplatesCache::getInstance()->getCachedTemplateClass($view) ?: $view;
        }

        $view = new $view($data);

        if (false === $view instanceof AbstractView) {
            throw new InvalidConfigurationException();
        }

        return new Response(view: $view, dataFromController: $data, useLayout: $useLayout);
    }
}
