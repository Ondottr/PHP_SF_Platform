<?php declare( strict_types=1 );

namespace PHP_SF\System\Attributes\Validator\Constraints;

use PHP_SF\System\Classes\Abstracts\AbstractConstraintValidator;

use function in_array;

/**
 * @property OneOfTheValues constraint
 */
final class OneOfTheValuesValidator extends AbstractConstraintValidator
{
    public function validate(): bool
    {
        $val = $this->getValue();

        if ( $this->isDefaultValue() )
            return true;


        if ( !in_array( $val, $this->constraint->arr, true ) ) {
            $this->setError(
                'one_of_the_values_validation_error',
                _t( $this->getTranslatablePropertyName() ),
                implode( ', ', $this->constraint->arr )
            );

            return false;
        }

        return true;
    }
}
