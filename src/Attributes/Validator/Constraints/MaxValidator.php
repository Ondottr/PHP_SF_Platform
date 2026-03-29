<?php declare( strict_types=1 );

namespace PHP_SF\System\Attributes\Validator\Constraints;

use PHP_SF\System\Classes\Abstracts\AbstractConstraintValidator;

/**
 * @property Max constraint
 */
final class MaxValidator extends AbstractConstraintValidator
{
    public function validate(): bool
    {
        if ( $this->isDefaultValue() )
            return true;


        if ( $this->getValue() > $this->constraint->value ) {
            $this->setError(
                'max_value_validation_error',
                _t( $this->getTranslatablePropertyName() ),
                $this->constraint->value
            );

            return false;
        }

        return true;
    }
}
