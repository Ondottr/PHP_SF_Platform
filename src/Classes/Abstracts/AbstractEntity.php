<?php declare( strict_types=1 );
/*
 * Copyright © 2018-2022, Nations Original Sp. z o.o. <contact@nations-original.com>
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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\Persistence\Proxy;
use JsonSerializable;
use PHP_SF\System\Attributes\Validator\TranslatablePropertyName;
use PHP_SF\System\Classes\Exception\InvalidEntityConfigurationException;
use PHP_SF\System\Core\DateTime;
use PHP_SF\System\Core\DoctrineCallbacksLoader;
use PHP_SF\System\Traits\EntityRepositoriesTrait;
use PHP_SF\System\Traits\ModelProperty\ModelPropertyIdTrait;
use ReflectionClass;
use ReflectionProperty;
use function array_key_exists;
use function assert;
use function count;
use function is_array;
use function is_object;


#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractEntity extends DoctrineCallbacksLoader implements JsonSerializable
{
    use ModelPropertyIdTrait;
    use EntityRepositoriesTrait;


    private static bool $__force_serialise__;
    private static array $entitiesList = [];
    private array $changedProperties;
    private array $validationErrors;


    public function __construct()
    {
        $this->setDefaultValues();
    }


    final public static function new(): static
    {
        return new static;
    }


    private function setDefaultValues(): void
    {
        $annotations = new AnnotationReader;
        $reflectionClass = new ReflectionClass( static::class );

        $annotations->getClassAnnotations( $reflectionClass );

        foreach ( self::getPropertiesList() as $property ) {
            if ( $property === 'id' )
                continue;

            $isNullable = false;

            $annotationProperty = $annotations
                ->getPropertyAnnotation( $reflectionClass->getProperty( $property ), ORM\Column::class );

            if ( $annotationProperty === null )
                $annotationProperty = $annotations
                    ->getPropertyAnnotation( $reflectionClass->getProperty( $property ), ORM\OneToOne::class );


            if ( $annotationProperty === null )
                $annotationProperty = $annotations
                    ->getPropertyAnnotation( $reflectionClass->getProperty( $property ), ORM\ManyToOne::class );


            if ( $annotationProperty instanceof ORM\Column ) {
                if ( isset( $annotationProperty->nullable ) )
                    $isNullable = $annotationProperty->nullable;


                if ( $isNullable ) {
                    $this->$property = null;

                    continue;
                }

                if (
                    property_exists( $annotationProperty, 'options' ) === false ||
                    array_key_exists( 'default', $annotationProperty->options ) === false
                )
                    continue;


                if ( property_exists( $annotationProperty, 'type' ) ) {
                    $defaultValue = $annotationProperty->options['default'];

                    $this->$property = match ( $annotationProperty->type ) {

                        Types::STRING, Types::TEXT => $defaultValue,

                        Types::ARRAY, Types::SIMPLE_ARRAY => j_decode( $defaultValue, true ),

                        Types::OBJECT, Types::JSON => j_decode( $defaultValue ),

                        Types::INTEGER, Types::SMALLINT, Types::BIGINT => (int)$defaultValue,

                        Types::FLOAT, Types::DECIMAL, Types::BLOB => (float)$defaultValue,

                        Types::BOOLEAN => (bool)$defaultValue,

                        Types::DATE_MUTABLE, Types::TIME_MUTABLE,
                        Types::DATETIME_MUTABLE => new DateTime

                    };
                }
            } elseif ( $annotationProperty instanceof ORM\ManyToOne || $annotationProperty instanceof ORM\OneToOne ) {
                $targetEntity = $annotationProperty->targetEntity;

                $annotationProperty = $annotations
                    ->getPropertyAnnotation( $reflectionClass->getProperty( $property ), ORM\JoinColumn::class );

                if ( isset( $annotationProperty->nullable ) )
                    $isNullable = $annotationProperty->nullable;


                if ( $isNullable ) {
                    $this->$property = null;

                    continue;
                }

                if ( isset( $annotationProperty->columnDefinition ) ) {
                    $arr = explode( 'DEFAULT ', $annotationProperty->columnDefinition );
                    $defaultValue = (int)end( $arr );

                    $this->$property = em()
                        ->getRepository( $targetEntity )
                        ->find( $defaultValue );
                }
            }
        }
    }

    private static function getPropertiesList(): array
    {
        $reflectionClass = new ReflectionClass( static::class );

        foreach ( $reflectionClass->getProperties( ReflectionProperty::IS_PROTECTED ) as $ReflectionProperty )
            $arr[] = $ReflectionProperty->getName();


        return $arr ?? [];
    }

    /**
     * Use this method if you want to create an instance of an existing entity <strong>without</strong> future saving to DB!
     */
    final public static function createFromParams( object|null $arr ): static|null
    {
        if ( $arr === null )
            return null;

        $entity = new ( static::class );

        foreach ( self::getPropertiesList() as $property ) {
            if ( is_array( $arr->$property ) ) {
                if ( isset( $arr->$property['id'] ) )
                    $entity->$property = $arr->$property['id'];

                elseif ( isset( $arr->$property['date'] ) )
                    $entity->$property = new DateTime( $arr->$property['date'] );

                else
                    $entity->$property = $arr->$property;

            } elseif ( is_object( $arr->$property ) && isset( $arr->$property->date ) )
                $entity->$property = new DateTime( $arr->$property->date );

            else
                $entity->$property = $arr->$property;

        }

        return $entity;
    }

    final public static function getClassName( string $className = null ): string
    {
        $className = $className ?: static::class;

        $arr = explode( '\\', $className );

        return end( $arr );
    }

    public static function clearQueryBuilderCache(): void
    {
        foreach ( rc()->keys( self::getClearQueryBuilderCacheKey() ) as $key )
            rc()->del( str_replace( sprintf( "%s:%s:", env( 'SERVER_PREFIX' ), env( 'APP_ENV' ) ), '', $key ) );

    }

    final protected static function getClearQueryBuilderCacheKey(): string
    {
        return sprintf( '*doctrine_result_cache:*' );
    }

    final public function validate( bool $isUpdated = false ): array|bool
    {
        $this->validationErrors = [];

        $reflectionClass = new ReflectionClass( static::class );

        $annotations = new AnnotationReader();
        $annotations->getClassAnnotations( $reflectionClass );

        $properties = $reflectionClass
            ->getProperties( ReflectionProperty::IS_PROTECTED );

        foreach ( $properties as $ReflectionProperty ) {
            $propertyName = $ReflectionProperty->getName();

            if ( isset( $this->changedProperties ) && !array_key_exists( $propertyName, $this->changedProperties ) )
                continue;


            $annotationProperty = $annotations->getPropertyAnnotation( $ReflectionProperty, ORM\Column::class );
            if ( $annotationProperty === null )
                $annotationProperty = $annotations->getPropertyAnnotation( $ReflectionProperty, ORM\JoinColumn::class );


            if ( $annotationProperty && $annotationProperty->unique === true ) {
                if ( isset( $this->$propertyName ) === false ) {
                    if ( $annotationProperty->nullable === true )
                        continue;


                    $this->validationErrors[ $propertyName ] =
                        _t('Field `%s` cannot be null.', _t( $this->getTranslatablePropertyName( $propertyName ) ) );

                    return $this->getValidationErrors();
                }

                if ( $isUpdated === false ) {
                    $propertyValue = $this->$propertyName;

                    $entity = em()
                        ->getRepository( static::class )
                        ->findOneBy( [ $propertyName => $propertyValue ] );

                    if ( $entity instanceof ( static::class ) ) {
                        $this->validationErrors[ $propertyName ] = _t(
                                'field_with_this_value_already_exists_validation_error',
                                _t( $this->getTranslatablePropertyName( $propertyName ) ), $propertyValue
                        );

                        return $this->getValidationErrors();
                    }
                }
            }
        }

        foreach ( $properties as $ReflectionProperty ) {
            $propertyName = $ReflectionProperty->getName();
            if ( $propertyName === 'id' )
                continue;

            if ( isset( $this->changedProperties ) && !array_key_exists( $propertyName, $this->changedProperties ) )
                continue;

            if ( count( $reflectionAttributes = $ReflectionProperty->getAttributes() ) === 0 )
                continue;


            unset( $annotationProperty );
            foreach ( $ReflectionProperty->getAttributes() as $ra )
                if ( $ra->getName() === Column::class )
                    $annotationProperty = $ra;

            if ( isset( $annotationProperty ) === false )
                foreach ( $ReflectionProperty->getAttributes() as $ra )
                    if ( $ra->getName() === JoinColumn::class )
                        $annotationProperty = $ra;


            foreach ( $reflectionAttributes as $reflectionAttribute ) {
                $validationConstraint = new ( $reflectionAttribute->getName() )( ...$reflectionAttribute->getArguments() );

                if ( isset( $this->$propertyName ) ) {
                    if ( $validationConstraint instanceof AbstractConstraint === false )
                        continue;

                    $validationConstraint->setPropertyName( $propertyName );

                    $validator = new ( $reflectionAttribute->getName() . 'Validator' )(
                        $this->$propertyName, $validationConstraint, $this
                    );
                    assert( $validator instanceof AbstractConstraintValidator );

                    $validator->validate();
                    if ( ( $err = $validator->getError() ) !== false )
                        $this->validationErrors[ $propertyName ] = $err;

                } elseif ( $annotationProperty !== null ) {
                    if ( array_key_exists( 'nullable', $annotationProperty->getArguments() ) ) {
                        if ( $annotationProperty->getArguments()['nullable'] === false )
                            $this->validationErrors[ $propertyName ] =
                                _t('Field `%s` cannot be null.', _t( $this->getTranslatablePropertyName( $propertyName ) ) );

                    } else
                        $this->validationErrors[ $propertyName ] =
                            _t('Field `%s` cannot be null.', _t( $this->getTranslatablePropertyName( $propertyName ) ) );

                    return $this->getValidationErrors();
                }
            }
        }

        return $this->getValidationErrors();
    }

    final public function getTranslatablePropertyName( string $propertyName ): string
    {
        if ( empty( $translatablePropertyName = ( new ReflectionProperty( static::class, $propertyName ) )
            ->getAttributes( TranslatablePropertyName::class )
        ) )
            throw new InvalidEntityConfigurationException(
                sprintf(
                    'The required attribute "PHP_SF\System\Attributes\Validator\TranslatablePropertyName" is missing in the property "%s" of the entity "%s".',
                    $propertyName, static::class
                )
            );

        return $translatablePropertyName[0]->getArguments()[0];
    }


    final public function getValidationErrors(): array|bool
    {
        return empty( $this->validationErrors ) ? true : $this->validationErrors;
    }


    final public function jsonSerialize(): array|int
    {
        if ( $this instanceof Proxy && self::isForceSerialiseEnabled() === false )
            return $this->id;

        $arr = [];

        foreach ( self::getPropertiesList() as $property )
            $arr[ $property ] = ( $this->$property instanceof self ) ? $this->$property->getId() : $this->$property;


        return $arr;
    }

    private static function isForceSerialiseEnabled(): bool
    {
        return isset( self::$__force_serialise__ ) && self::$__force_serialise__;
    }

}
