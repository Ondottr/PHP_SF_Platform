<?php declare(strict_types=1);

namespace PHP_SF\System\Core;

use PHP_SF\System\Kernel;
use Symfony\Component\HttpFoundation\Request;

final class PhpSfContext
{
    private static ?self $current = null;

    public function __construct(
        private readonly object $route,
        private readonly array $middleware,
        private readonly Request $request,
        private readonly Kernel $kernel,
    ) {
    }

    public static function set(self $ctx): void
    {
        self::$current = $ctx;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public function getRoute(): object
    {
        return $this->route;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getKernel(): Kernel
    {
        return $this->kernel;
    }
}
