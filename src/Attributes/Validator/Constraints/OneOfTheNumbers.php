<?php declare( strict_types=1 );

namespace PHP_SF\System\Attributes\Validator\Constraints;

use Attribute;
use PHP_SF\System\Classes\Abstracts\AbstractConstraint;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use function is_int;

#[Attribute( Attribute::TARGET_PROPERTY )]
class OneOfTheNumbers extends AbstractConstraint
{
    public function __construct( public array $numbers )
    {
        foreach ( $numbers as $number ) {
            if ( !is_int( $number ) ) {
                throw new UnexpectedValueException( $number, 'integer' );
            }
        }
    }
}
