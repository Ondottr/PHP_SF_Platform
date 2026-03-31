<?php declare( strict_types=1 );

namespace PHP_SF\System\Attributes\Validator\Constraints;

use PHP_SF\System\Classes\Abstracts\AbstractConstraintValidator;
use function in_array;

/**
 * @property OneOfTheNumbers constraint
 */
final class OneOfTheNumbersValidator extends AbstractConstraintValidator
{
    public function validate(): bool
    {
        $val = $this->getValue();

        if ( $this->isDefaultValue() )
            return true;


        if ( !in_array( $val, $this->constraint->numbers, true ) ) {
            $this->setError(
                'Field `%s` must be one of these numbers: (%s)',
                _t( $this->getTranslatablePropertyName() ),
                implode( ', ', $this->constraint->numbers )
            );

            return false;
        }

        return true;
    }
}
