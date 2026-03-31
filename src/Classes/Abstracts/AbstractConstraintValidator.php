<?php declare( strict_types=1 );

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
