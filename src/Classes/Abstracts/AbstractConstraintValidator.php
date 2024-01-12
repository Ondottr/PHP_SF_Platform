<?php declare( strict_types=1 );
/*
 * Copyright © 2018-2024, Nations Original Sp. z o.o. <contact@nations-original.com>
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

namespace PHP_SF\System\Classes\Abstracts;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Driver\AttributeReader;
use PHP_SF\System\Attributes\Validator\TranslatablePropertyName;
use PHP_SF\System\Classes\Exception\InvalidEntityConfigurationException;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

use function array_key_exists;

abstract class AbstractConstraintValidator
{

    private string|false $error = false;


    public function __construct(
        private mixed                $value,
        protected AbstractConstraint $constraint,
        protected AbstractEntity     $validatedClass
    ) {
        $arr             = explode( 'Validator', static::class );
        $constraintClass = sprintf( '%sValidator%s', $arr[0], $arr[1] );

        if ( !$this->constraint instanceof $constraintClass )
            throw new UnexpectedTypeException( $this->constraint, $constraintClass );

    }


    abstract public function validate(): bool;

    final public function getError(): string|false
    {
        return $this->error;
    }

    final public function setError( string $error, ...$values ): void
    {
        $this->error = _t( $error, ...$values );
    }

    final protected function getTranslatablePropertyName(): string
    {
        $rp = new ReflectionProperty( $this->getValidatedClass()::class, $this->constraint->getPropertyName() );
        $tpn = $rp->getAttributes( TranslatablePropertyName::class );

        if ( empty( $tpn ) )
            throw new InvalidEntityConfigurationException(
                sprintf(
                    'The required attribute "PHP_SF\System\Attributes\Validator\TranslatablePropertyName" is missing in the property "%s" of the entity "%s".',
                    $rp->getName(), $this->getValidatedClass()::class
                )
            );

        return $tpn[0]->getArguments()[0];
    }

    final public function getValidatedClass(): AbstractEntity
    {
        return $this->validatedClass;
    }

    final protected function isDefaultValue(): bool
    {
        ( $annotations = new AttributeReader )
            ->getClassAttributes( new ReflectionClass( $this->validatedClass::class ) );

        $annotationProperty = $annotations->getPropertyAttribute(
            new ReflectionProperty( $this->getValidatedClass()::class, $this->constraint->getPropertyName() ),
            Column::class
        );

        if ( $annotationProperty?->options )
            if ( array_key_exists( 'default', $annotationProperty->options ) )
                if ( $annotationProperty->options['default'] == $this->getValue() )
                    return true;

        return false;
    }

    final protected function getValue(): mixed
    {
        return $this->value;
    }
}
