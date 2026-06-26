<?php /** @noinspection PhpPropertyOnlyWrittenInspection */
declare(strict_types=1);

namespace PHP_SF\System\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    /**
     * @param string|array<array-key, mixed> $middleware
     */
    public function __construct(
        public readonly string $url,
        public readonly string $httpMethod,
        public readonly ?string $name = null,
        public readonly string|array $middleware = [],
    ) {}
}
