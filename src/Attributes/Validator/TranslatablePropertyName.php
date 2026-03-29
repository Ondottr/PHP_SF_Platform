<?php declare( strict_types=1 );

namespace PHP_SF\System\Attributes\Validator;

use Attribute;

#[Attribute( Attribute::TARGET_PROPERTY )]
class TranslatablePropertyName
{
    public function __construct( string $name ) {}
}
