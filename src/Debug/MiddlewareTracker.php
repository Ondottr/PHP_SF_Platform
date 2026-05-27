<?php declare(strict_types=1);

namespace PHP_SF\System\Debug;

final class MiddlewareTracker
{
    private static array $log = [];

    public static function record(string $class, bool $passed): void
    {
        self::$log[] = ['middleware' => $class, 'passed' => $passed];
    }

    public static function getLog(): array
    {
        return self::$log;
    }
}
