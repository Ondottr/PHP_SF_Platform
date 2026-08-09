<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Abstracts;

use PHP_SF\System\Core\ApiResponse;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Debug\MiddlewareTracker;
use PHP_SF\System\Kernel;
use PHP_SF\System\Router;
use PHP_SF\System\Traits\RedirectTrait;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class Middleware
{
    use RedirectTrait;


    abstract protected function result(): bool|JsonResponse|RedirectResponse;

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
            if (Router::isApiRoute()) {
                $middlewareResult = ApiResponse::forbidden();
            } else {
                $middlewareResult = $this->redirectBack(errors: [_t('common.errors.access_denied')]);
            }
        }

        return $middlewareResult;
    }
}
