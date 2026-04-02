<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\Proxy;
use JsonSerializable;
use PHP_SF\System\Attributes\Validator\TranslatablePropertyName;
use PHP_SF\System\Classes\Exception\InvalidEntityConfigurationException;
use PHP_SF\System\Core\DoctrineCallbacksLoader;
use PHP_SF\System\Traits\EntityRepositoriesTrait;
use PHP_SF\System\Traits\ModelProperty\ModelPropertyIdTrait;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Validator\Validation;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractEntity extends DoctrineCallbacksLoader implements JsonSerializable
{

    use ModelPropertyIdTrait;
    use EntityRepositoriesTrait;


    private static array $entitiesList     = [];
    private array        $validationErrors = [];


    final public static function new(): static
    {
        return new static();
    }


    public static function clearQueryBuilderCache(): void
    {
        ca()->deleteByKeyPattern( '*doctrine_result_cache:*' );
    }

    final public function validate(): bool
    {
        $this->validationErrors = [];

        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate( $this );

        foreach ( $violations as $violation ) {
            $pName = $violation->getPropertyPath();

            $this->validationErrors[ $pName ] = sprintf(
                'Field `%s`: %s',
                _t( $this->getTranslatablePropertyName( $pName ) ),
                rtrim( (string) $violation->getMessage(), '.' )
            );
        }

        return empty( $this->validationErrors );
    }

    final public function getTranslatablePropertyName( string $propertyName ): string
    {
        $rp  = new ReflectionProperty( static::class, $propertyName );
        $tpn = $rp->getAttributes( TranslatablePropertyName::class );

        if ( empty( $tpn ) )
            throw new InvalidEntityConfigurationException(
                sprintf(
                    'The required attribute "PHP_SF\System\Attributes\Validator\TranslatablePropertyName" is missing in the property "%s" of the entity "%s".',
                    $rp->getName(),
                    static::class
                )
            );

        return $tpn[0]->getArguments()[0];
    }


    final public function getValidationErrors(): array|bool
    {
        if ( empty( $this->validationErrors ) )
            return true;

        return $this->validationErrors;
    }


    final public function jsonSerialize(): array|int
    {
        if ( $this instanceof Proxy )
            return $this->id;

        $arr = [];

        $reflectionClass = new ReflectionClass( static::class );

        $properties = [];
        foreach ( $reflectionClass->getProperties( ReflectionProperty::IS_PROTECTED ) as $ReflectionProperty )
            $properties[] = $ReflectionProperty->getName();

        foreach ( $properties as $property )
            $arr[ $property ] = ( $this->$property instanceof self ) ? $this->$property->getId() : $this->$property;

        return $arr;
    }

}
