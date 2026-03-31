<?php declare( strict_types=1 );

namespace PHP_SF\System\Attributes\Validator\Constraints;

use PHP_SF\System\Classes\Abstracts\AbstractConstraintValidator;

/**
 * @property DateTime constraint
 */
final class DateTimeValidator extends AbstractConstraintValidator
{
    public function validate(): bool
    {
        if ( $this->constraint->allowNull === true && $this->getValue() === null )
            return true;

        if ( $this->getValue() instanceof \PHP_SF\System\Core\DateTime === false ) {
            $this->setError( 'datetime_validation_error', _t( $this->getTranslatablePropertyName() ) );

            return false;
        }

        return true;
    }
}
