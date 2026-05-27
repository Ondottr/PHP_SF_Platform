<?php /** @noinspection PhpPropertyOnlyWrittenInspection */
declare(strict_types=1);

namespace PHP_SF\System\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        private readonly string $url,
        private readonly string $httpMethod,
        private readonly ?string $name = null,
        private readonly string|array $middleware = [],
    ) {
    }
}
