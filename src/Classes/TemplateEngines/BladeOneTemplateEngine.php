<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\TemplateEngines;

use eftec\bladeone\BladeOne;
use PHP_SF\System\Interface\TemplateEngineInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Renders `*.blade.php` templates through the standalone BladeOne compiler.
 * Views live in `templates_blade/`, compiled templates are cached in
 * `var/cache/bladeone`. Template references use the file path relative to
 * the views directory, e.g. `pages/dashboard.blade.php`.
 */
final class BladeOneTemplateEngine implements TemplateEngineInterface
{
    private const FILE_EXTENSION = '.blade.php';

    private ?BladeOne $blade = null;


    public function __construct(
        private readonly string $viewsDirectory = 'templates_blade',
        private readonly string $cacheDirectory = 'var/cache/bladeone',
    ) {}


    public function supports(string $template): bool
    {
        return str_ends_with($template, self::FILE_EXTENSION);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        return $this->blade()->run($template, $data);
    }

    private function blade(): BladeOne
    {
        if ($this->blade instanceof BladeOne) {
            return $this->blade;
        }

        $viewsPath = project_dir() . '/' . $this->viewsDirectory;
        if (false === is_dir($viewsPath)) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Blade views directory "%s" does not exist. Create it in the project root '
                    . 'or register a custom ' . BladeOneTemplateEngine::class . ' with a different path.',
                    $viewsPath,
                ),
            );
        }

        $cachePath = project_dir() . '/' . $this->cacheDirectory;
        if (false === is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $this->blade = new BladeOne(
            $viewsPath,
            $cachePath,
            DEV_MODE ? BladeOne::MODE_AUTO : BladeOne::MODE_FAST,
        );

        // Reuse the framework CSRF token instead of BladeOne's own session-based one,
        // so `@csrf` matches what the csrf middleware validates.
        $this->blade->csrf_token = csrf_token();

        $this->blade->errorCallBack = static function (?string $key = null): string|false {
            if (null === $key) {
                return false;
            }

            $errors = getErrors($key);

            if (is_string($errors)) {
                return $errors;
            }

            if (is_array($errors) && [] !== $errors) {
                return (string) reset($errors);
            }

            return false;
        };

        return $this->blade;
    }
}
