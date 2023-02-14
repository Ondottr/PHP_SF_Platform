<?php /** @noinspection PhpPropertyOnlyWrittenInspection */
declare( strict_types=1 );

namespace PHP_SF\System\Attributes;

use Attribute;

#[Attribute( Attribute::TARGET_METHOD )]
class Route
{

    public function __construct(
        private readonly string       $url,
        private readonly string       $httpMethod,
        private readonly string|null  $name = null,
        private readonly string|array $middleware = []
    ) {}

}
