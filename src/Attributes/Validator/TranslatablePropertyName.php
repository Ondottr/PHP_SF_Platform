<?php declare( strict_types=1 );

namespace PHP_SF\System\Attributes\Validator;

use Attribute;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated( reason: 'This attribute is no longer needed as the validator now uses the property name by default if no custom name is provided.' ) ]
#[Attribute( Attribute::TARGET_PROPERTY )]
class TranslatablePropertyName
{
    public function __construct( string $name ) {}
}
