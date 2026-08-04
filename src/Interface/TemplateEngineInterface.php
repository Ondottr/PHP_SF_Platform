<?php declare(strict_types=1);

namespace PHP_SF\System\Interface;

interface TemplateEngineInterface
{
    /**
     * Returns true when this engine can render the given template reference.
     */
    public function supports(string $template): bool;

    /**
     * Renders the template and returns the resulting HTML string.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string;
}
