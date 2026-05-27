<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Abstracts;

use PHP_SF\System\Core\ApiResponse;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Debug\MiddlewareTracker;
use PHP_SF\System\Kernel;
use PHP_SF\System\Router;
use PHP_SF\System\Traits\RedirectTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class Middleware
{
    use RedirectTrait;

    public function __construct(
        protected readonly ?Request $request,
        private readonly Kernel $kernel,
    ) {
    }

    final public function execute(): bool|JsonResponse|RedirectResponse
    {
        $middlewareResult = $this->result();

        if (DEV_MODE) {
            MiddlewareTracker::record(static::class, true === $middlewareResult);
        }

        if (true === $middlewareResult) {
            return true;
        }

        if (false === $middlewareResult) {
            if (str_starts_with(Router::$currentRoute->url, '/api/')) {
                $middlewareResult = ApiResponse::forbidden();
            } else {
                $middlewareResult = $this->redirectBack(errors: [_t('common.errors.access_denied')]);
            }
        }

        return $middlewareResult;
    }

    abstract protected function result(): bool|JsonResponse|RedirectResponse;

    final protected function changeHeaderTemplateClassName(string $headerClassName): void
    {
        $this->kernel->setHeaderTemplateClassName($headerClassName);
    }

    final protected function changeFooterTemplateClassName(string $footerClassName): void
    {
        $this->kernel->setFooterTemplateClassName($footerClassName);
    }
}
