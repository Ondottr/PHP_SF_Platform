<?php declare(strict_types=1);

namespace PHP_SF\Tests\System\Core\Fixtures;

use PHP_SF\System\Interface\TemplateEngineInterface;

final class StubTemplateEngine implements TemplateEngineInterface
{
    public function __construct(
        private readonly string $suffix,
    ) {}


    public function supports(string $template): bool
    {
        return str_ends_with($template, $this->suffix);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        return "rendered: $template";
    }
}
