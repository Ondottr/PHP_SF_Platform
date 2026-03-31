<?php declare( strict_types=1 );

namespace PHP_SF\System\Attributes\Validator\Constraints;

use PHP_SF\System\Classes\Abstracts\AbstractConstraintValidator;

/**
 * @property Email constraint
 */
final class EmailValidator extends AbstractConstraintValidator
{
    public function validate(): bool
    {
        if ( !filter_var( $this->getValue(), FILTER_VALIDATE_EMAIL ) ) {
            $this->setError(
                'Field `%s` is not a valid email address.',
                _t( $this->getTranslatablePropertyName() )
            );

            return false;
        }

        return true;
    }
}
