<?php declare( strict_types=1 );

namespace PHP_SF\System\Attributes\Validator\Constraints;

use Attribute;
use PHP_SF\System\Classes\Abstracts\AbstractConstraint;

#[Attribute( Attribute::TARGET_PROPERTY )]
final class DateTime extends AbstractConstraint
{

    public function __construct( public bool|null $allowNull = null )
    {
        $this->allowNull = $this->allowNull ?? false;
    }

}
