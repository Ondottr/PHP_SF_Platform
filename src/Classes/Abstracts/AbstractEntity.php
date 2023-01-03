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

use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\Proxy;
use JsonSerializable;
use PHP_SF\System\Attributes\Validator\TranslatablePropertyName;
use PHP_SF\System\Classes\Exception\InvalidEntityConfigurationException;
use PHP_SF\System\Core\DateTime;
use PHP_SF\System\Core\DoctrineCallbacksLoader;
use PHP_SF\System\Database\DoctrineEntityManager;
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


    private static bool  $__force_serialise__;
    private static array $entitiesList = [];
    private array        $changedProperties;
    private string       $serverName = SERVER_NAME;
    private array        $validationErrors;


    public function __construct( bool $isCacheEnabled = true )
    {
        $this->setDefaultValues( $isCacheEnabled );
    }

    /** @noinspection PhpVariableVariableInspection */

    private function setDefaultValues( bool $isCacheEnabled = true ): void
    {
        $annotations     = new AnnotationReader();
        $reflectionClass = new ReflectionClass( static::class );

        $annotations->getClassAnnotations( $reflectionClass );

        foreach ( self::getPropertiesList() as $property ) {
            if ( $property === 'id' )
                continue;

            $isNullable = false;

            $annotationProperty = $annotations
                ->getPropertyAnnotation(
                    $reflectionClass->getProperty( $property ),
                    ORM\Column::class
                );

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

                        Types::STRING, Types::TEXT                     => $defaultValue,

                        Types::ARRAY, Types::SIMPLE_ARRAY              => j_decode( $defaultValue, true ),

                        Types::OBJECT, Types::JSON                     => j_decode( $defaultValue ),

                        Types::INTEGER, Types::SMALLINT, Types::BIGINT => (int)$defaultValue,

                        Types::FLOAT, Types::DECIMAL, Types::BLOB      => (float)$defaultValue,

                        Types::BOOLEAN                                 => (bool)$defaultValue,

                        Types::DATE_MUTABLE, Types::TIME_MUTABLE,
                        Types::DATETIME_MUTABLE                        => new DateTime

                    };
                }
            }
            elseif ( $annotationProperty instanceof ORM\ManyToOne || $annotationProperty instanceof ORM\OneToOne ) {
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
                    $arr          = explode( 'DEFAULT ', $annotationProperty->columnDefinition );
                    $defaultValue = (int)end( $arr );

                    $this->$property = em( $isCacheEnabled )
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
     * Use this method if you want to create an instance of an existing entity <strong>without</strong> future saving
     * to DB!
     *
     * @noinspection PhpVariableVariableInspection
     */
    final public static function createFromParams( object|null $arr, string $serverName = null ): static|null
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

            }
            elseif ( is_object( $arr->$property ) && isset( $arr->$property->date ) )
                $entity->$property = new DateTime( $arr->$property->date );

            else
                $entity->$property = $arr->$property;

        }

        if ( $serverName !== null )
            $entity->setServerName( $serverName );

        return $entity;
    }

    public static function clearRepositoryCache(): void
    {
        foreach ( rc()->keys( self::getClearRepositoryCacheKey() ) as $key )
            rc()->del( $key );

    }

    final protected static function getClearRepositoryCacheKey(): string
    {
        return sprintf( '%s:cache:repository:*%s*', SERVER_NAME, self::getClassName() );
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
            rc()->del( $key );

    }

    final protected static function getClearQueryBuilderCacheKey(): string
    {
        return sprintf(
            '%s:cache:queryBuilder:*%s*',
            SERVER_NAME,
            str_replace( '\\', '\\\\', static::class )
        );
    }

    public static function getEntitiesList(): array
    {
        if ( !empty( self::$entitiesList ) )
            return self::$entitiesList;


        $entities = [];
        foreach ( DoctrineEntityManager::getEntityDirectories() as $item )
            $entities = array_merge( array_diff( scandir( $item ), [ '.', '..' ] ) );


        foreach ( $entities as $key => $entity )
            $entities[ $key ] = 'App\Entity\\' . str_replace( '.php', '', $entity );


        return ( self::$entitiesList = $entities );
    }

    final public static function setForceSerialise( bool $_force_serialise ): string
    {
        self::$__force_serialise__ = $_force_serialise;

        return static::class;
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
                if ( !isset( $this->$propertyName ) ) {
                    if ( $annotationProperty->nullable === true )
                        continue;


                    $this->validationErrors[ $propertyName ] =
                        _t( sprintf( '%s_cannot_be_null', $this->getTranslatablePropertyName( $propertyName ) ) );

                    return $this->getValidationErrors();
                }

                if ( $isUpdated === false ) {
                    $propertyValue = $this->$propertyName;

                    $entity = em()
                        ->getRepository( static::class )
                        ->findOneBy( [ $propertyName => $propertyValue ] );

                    if ( $entity instanceof ( static::class ) ) {
                        $this->validationErrors[ $propertyName ] = _t(
                            sprintf(
                                '%s_with_this_value_already_exists',
                                $this->getTranslatablePropertyName( $propertyName )
                            ),
                            $propertyValue
                        );

                        return $this->getValidationErrors();
                    }
                }
            }
        }

        foreach ( $properties as $ReflectionProperty ) {
            $propertyName = $ReflectionProperty->getName();
            if( $propertyName === 'id' )
                continue;

            if ( isset( $this->changedProperties ) && !array_key_exists( $propertyName, $this->changedProperties ) )
                continue;

            if ( count( $reflectionAttributes = $ReflectionProperty->getAttributes() ) === 0 )
                continue;


            $annotationProperty = $annotations
                ->getPropertyAnnotation( $ReflectionProperty, ORM\Column::class );

            if ( $annotationProperty === null )
                $annotationProperty = $annotations
                    ->getPropertyAnnotation( $ReflectionProperty, ORM\JoinColumn::class );


            foreach ( $reflectionAttributes as $reflectionAttribute ) {
                $validationConstraint = new ( $reflectionAttribute->getName() )(
                    ...$reflectionAttribute->getArguments()
                );

                if ( isset( $this->$propertyName ) ) {
                    if ( $validationConstraint instanceof AbstractConstraint === false )
                        continue;

                    $validationConstraint->setPropertyName( $propertyName );

                    $validator = new ( $reflectionAttribute->getName() . 'Validator' )(
                        $this->$propertyName,
                        $validationConstraint,
                        $this
                    );
                    assert( $validator instanceof AbstractConstraintValidator );

                    $validator->validate();
                    if ( ( $err = $validator->getError() ) !== false )
                        $this->validationErrors[ $propertyName ] = $err;

                }
                elseif ( !$annotationProperty || $annotationProperty->nullable !== true ) {
                    $this->validationErrors[ $propertyName ] =
                        _t(
                            sprintf( '%s_field_cannot_be_empty', $this->getTranslatablePropertyName( $propertyName ) )
                        );

                    return $this->getValidationErrors();
                }
            }
        }

        return $this->getValidationErrors();
    }

    final public function getTranslatablePropertyName( string $propertyName ): string
    {
        if ( empty(
        $translatablePropertyName = ( new ReflectionProperty( static::class, $propertyName ) )
            ->getAttributes( TranslatablePropertyName::class )
        ) ) {
            throw new InvalidEntityConfigurationException(
                sprintf(
                    'The required attribute "PHP_SF\System\Attributes\Validator\TranslatablePropertyName" is missing in the "%s" property in the "%s" class',
                    $propertyName, static::class
                )
            );
        }

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

    final public function getServerName(): string
    {
        return $this->serverName;
    }

    final public function setServerName( string $serverName ): void
    {
        $this->serverName = $serverName;
    }

}
