<?php /** @noinspection PhpPropertyOnlyWrittenInspection */
declare( strict_types=1 );

namespace PHP_SF\System\Attributes;

use Attribute;

#[Attribute( Attribute::TARGET_METHOD )]
class Route
{

    public function __construct(
        private string            $url,
        private string            $httpMethod,
        private ?string           $name = null,
        private null|string|array $middleware = null
    ) {}

}
