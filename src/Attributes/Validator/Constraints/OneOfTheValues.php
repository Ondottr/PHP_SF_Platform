<?php declare( strict_types=1 );

namespace PHP_SF\System\Attributes\Validator\Constraints;

use Attribute;
use PHP_SF\System\Classes\Abstracts\AbstractConstraint;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

use function is_int;
use function is_string;

#[Attribute( Attribute::TARGET_PROPERTY )]
class OneOfTheValues extends AbstractConstraint
{
    public function __construct( public array $arr )
    {
        foreach ( $arr as $value )
            if ( is_string( $value ) === false && is_int( $value ) === false )
                throw new UnexpectedValueException( $value, 'string|integer' );

    }
}
