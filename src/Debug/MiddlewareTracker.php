<?php declare(strict_types=1);

namespace PHP_SF\System\Debug;

final class MiddlewareTracker
{
    /**
     * @var list<array{middleware: string, passed: bool}>
     */
    private static array $log = [];

    public static function record(string $class, bool $passed): void
    {
        self::$log[] = ['middleware' => $class, 'passed' => $passed];
    }

    /**
     * @return list<array{middleware: string, passed: bool}>
     */
    public static function getLog(): array
    {
        return self::$log;
    }
}
