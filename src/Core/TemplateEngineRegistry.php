<?php declare(strict_types=1);

namespace PHP_SF\System\Core;

use PHP_SF\System\Classes\TemplateEngines\BladeOneTemplateEngine;
use PHP_SF\System\Classes\TemplateEngines\TwigTemplateEngine;
use PHP_SF\System\Interface\TemplateEngineInterface;

/**
 * Registry of template engines available to PHP_SF controllers.
 *
 * Views passed as class names are rendered by the classic class-based mechanism;
 * views referenced by template file name are dispatched to the first registered
 * engine that supports them (e.g. `*.twig`, `*.blade.php`). Engines are optional:
 * built-in engines register themselves lazily on first resolution and only when
 * their backing library is installed in the host application, so any combination
 * of plain-PHP class views, Twig and Blade can coexist in one application.
 */
final class TemplateEngineRegistry
{
    /**
     * @var list<TemplateEngineInterface>
     */
    private static array $engines = [];

    private static bool $builtInEnginesRegistered = false;


    private function __construct() {}


    public static function add(TemplateEngineInterface $engine): void
    {
        self::$engines[] = $engine;
    }

    /**
     * Returns the first engine that supports the given template reference,
     * or null when none does (e.g. for class-based views).
     */
    public static function resolve(string $template): ?TemplateEngineInterface
    {
        self::registerBuiltInEngines();

        foreach (self::$engines as $engine) {
            if ($engine->supports($template)) {
                return $engine;
            }
        }

        return null;
    }

    /**
     * Derives the CSS class used to wrap rendered engine output, mirroring
     * the short-class-name wrapper of class-based views:
     * `pages/dashboard.html.twig` becomes `dashboard`.
     */
    public static function templateCssClass(string $template): string
    {
        $name = basename($template);

        return (string) preg_replace('/\.(html\.twig|twig|blade\.php)$/', '', $name);
    }

    /**
     * @internal used by tests to reset state between cases
     */
    public static function reset(): void
    {
        self::$engines = [];
        self::$builtInEnginesRegistered = false;
    }

    private static function registerBuiltInEngines(): void
    {
        if (self::$builtInEnginesRegistered) {
            return;
        }

        self::$builtInEnginesRegistered = true;

        if (class_exists(\Twig\Environment::class) && class_exists(\App\Kernel::class)) {
            self::$engines[] = new TwigTemplateEngine();
        }

        if (class_exists(\eftec\bladeone\BladeOne::class)) {
            self::$engines[] = new BladeOneTemplateEngine();
        }
    }
}
