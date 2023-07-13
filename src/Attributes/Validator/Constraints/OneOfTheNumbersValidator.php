<?php declare( strict_types=1 );
/*
 * Copyright © 2018-2023, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 * granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 * INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 * LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 * RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 * TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

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
