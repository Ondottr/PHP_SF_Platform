<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Abstracts;

use PHP_SF\System\Core\Response;
use PHP_SF\System\Core\TemplatesCache;
use PHP_SF\System\Traits\JsonResponseHelperTrait;
use PHP_SF\System\Traits\RedirectTrait;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

abstract class AbstractController
{
    use RedirectTrait;
    use JsonResponseHelperTrait;


    /**
     * @param array<string, mixed> $data
     */
    final protected function render( string $view, array $data = [], string $pageTitle = null ): Response
    {
        s()->set('page_title', $pageTitle ?? APPLICATION_NAME);

        if (TEMPLATES_CACHE_ENABLED) {
            $view = TemplatesCache::getInstance()->getCachedTemplateClass($view) ?: $view;
        }

        $view = new $view($data);

        if (false === $view instanceof AbstractView) {
            throw new InvalidConfigurationException();
        }

        return new Response(view: $view, dataFromController: $data);
    }
}
