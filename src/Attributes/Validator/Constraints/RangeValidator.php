<?php declare( strict_types=1 );

namespace PHP_SF\System\Attributes\Validator\Constraints;

use PHP_SF\System\Classes\Abstracts\AbstractConstraintValidator;

/**
 * @property Range constraint
 */
final class RangeValidator extends AbstractConstraintValidator
{
    public function validate(): bool
    {
        if ( $this->isDefaultValue() )
            return true;

        if ( $this->constraint->allowNull === true && $this->getValue() === null )
            return true;

        if ( $this->getValue() < $this->constraint->min || $this->getValue() > $this->constraint->max ) {
            $this->setError(
                'Field `%s` should be between `%s` and `%s`!',
                _t( $this->getTranslatablePropertyName() ),
                $this->constraint->min,
                $this->constraint->max
            );

            return false;
        }

        return true;
    }
}
