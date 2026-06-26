<?php declare(strict_types=1);

namespace PHP_SF\System\Core;

use PHP_SF\System\Kernel;

final class PhpSfContext
{
    private static ?self $current = null;


    /**
     * @param array<array-key, mixed> $middleware
     */
    public function __construct(
        private readonly object $route,
        private readonly array $middleware,
        private readonly Kernel $kernel,
    ) {}


    public function getRoute(): object
    {
        return $this->route;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getKernel(): Kernel
    {
        return $this->kernel;
    }

    public static function set(self $ctx): void
    {
        self::$current = $ctx;
    }

    public static function current(): ?self
    {
        return self::$current;
    }
}
