<?php declare( strict_types=1 );

namespace PHP_SF\System\Attributes\Validator\Constraints;

use PHP_SF\System\Classes\Abstracts\AbstractConstraintValidator;
use function strlen;

/**
 * @property Length constraint
 */
final class LengthValidator extends AbstractConstraintValidator
{
    public function validate(): bool
    {
        if ( $this->isDefaultValue() )
            return true;

        if ( $this->constraint->allowNull === true && $this->getValue() === null )
            return true;

        $length = strlen( $this->getValue() );

        if ( $length < $this->constraint->min ) {
            $this->setError(
                'Field %s is too short. It should have %s character or more.',
                _t( $this->getTranslatablePropertyName() ),
                $this->constraint->min
            );

            return false;
        }

        if ( $length > $this->constraint->max ) {
            $this->setError(
                'Field %s is too long. It should have %s character or less.',
                _t( $this->getTranslatablePropertyName() ),
                $this->constraint->max
            );

            return false;
        }

        return true;
    }
}
