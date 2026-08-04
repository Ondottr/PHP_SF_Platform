<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\TemplateEngines;

use App\Kernel;
use Closure;
use PHP_SF\System\Interface\TemplateEngineInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Twig\Environment;

/**
 * Renders `*.twig` templates through the Symfony-configured Twig environment,
 * so PHP_SF controller views share template paths, extensions and cache with
 * Twig templates rendered by Symfony controllers (`config/packages/twig.yaml`).
 */
final class TwigTemplateEngine implements TemplateEngineInterface
{
    /**
     * @param Closure():Environment|null $environmentFactory Optional custom provider of the Twig
     *                                                       environment. Defaults to fetching it from the application container, which requires a
     *                                                       public alias in `config/services.yaml`:
     *
     *     Twig\Environment: { alias: twig, public: true }
     */
    public function __construct(
        private readonly ?Closure $environmentFactory = null,
    ) {}


    public function supports(string $template): bool
    {
        return str_ends_with($template, '.twig');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        return $this->environment()->render($template, $data);
    }

    private function environment(): Environment
    {
        if (null !== $this->environmentFactory) {
            return ($this->environmentFactory)();
        }

        $container = Kernel::getInstance()->getContainer();

        if (false === $container->has(Environment::class)) {
            throw new InvalidConfigurationException(
                sprintf(
                    'The "%s" service is not available in the container. Add a public alias to '
                    . 'config/services.yaml: `Twig\Environment: { alias: twig, public: true }`.',
                    Environment::class,
                ),
            );
        }

        $environment = $container->get(Environment::class);

        if (false === $environment instanceof Environment) {
            throw new InvalidConfigurationException(
                sprintf('Container service "%s" is not a "%s" instance.', Environment::class, Environment::class),
            );
        }

        return $environment;
    }
}
