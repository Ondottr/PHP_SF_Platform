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

namespace PHP_SF\System\Classes\Abstracts;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\Proxy;
use JsonSerializable;
use PHP_SF\System\Attributes\Validator\TranslatablePropertyName;
use PHP_SF\System\Classes\Exception\InvalidEntityConfigurationException;
use PHP_SF\System\Core\DoctrineCallbacksLoader;
use PHP_SF\System\Traits\EntityRepositoriesTrait;
use PHP_SF\System\Traits\ModelProperty\ModelPropertyIdTrait;
use ReflectionClass;
use ReflectionProperty;
use function array_key_exists;
use function assert;
use function count;

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
        return new static;
    }


    public static function clearQueryBuilderCache(): void
    {
        ca()->deleteByKeyPattern( '*doctrine_result_cache:*' );
    }

    /**
     * @throws NonUniqueResultException
     *
     * @return bool
     */
    final public function validate(): bool
    {
        $rc                   = new ReflectionClass( static::class );
        $reflectionProperties = $rc->getProperties( ReflectionProperty::IS_PROTECTED );

        $attributes = new ORM\Driver\AttributeReader();

        // Validate nullable and unique fields
        foreach ( $reflectionProperties as $rp ) {
            $pName = $rp->getName();

            $ap = $attributes->getPropertyAttribute( $rp, ORM\Column::class );
            if ( $ap === null )
                $ap = $attributes->getPropertyAttributeCollection( $rp, ORM\JoinColumn::class )->getIterator()->current();

            if ( $ap !== null && $ap->unique !== false ) {
                if ( isset( $this->{$pName} ) === false ) {
                    if ( $ap->nullable === true )
                        continue;


                    $this->validationErrors[ $pName ] =
                        _t( 'Field `%s` cannot be null.',
                            _t( $this->getTranslatablePropertyName( $pName ) )
                        );

                    return false;
                }

                $pValue = $this->{$pName};

                $query = qb()
                    ->select( 'e' )
                    ->from( static::class, 'e' )
                    ->where( 'e.' . $pName . ' = :pValue' )
                    ->setParameter( 'pValue', $pValue );

                if ( isset( $this->id ) )
                    $query->andWhere( 'e.id != :id' )
                        ->setParameter( 'id', $this->id );

                $entity = $query->getQuery()
                    ->getOneOrNullResult();

                if ( $entity instanceof ( static::class ) ) {
                    $this->validationErrors[ $pName ] = _t(
                        'Field `%s` with value "%s" already exists!',
                        _t( $this->getTranslatablePropertyName( $pName ) ),
                        $pValue
                    );

                    return false;
                }
            }
        }

        // Run validator constraints
        foreach ( $reflectionProperties as $rp ) {
            $pName = $rp->getName();
            if ( $pName === 'id' )
                continue;

            if ( count( $reflectionAttributes = $rp->getAttributes() ) === 0 )
                continue;


            unset( $ap );
            foreach ( $rp->getAttributes() as $ra )
                if ( $ra->getName() === Column::class )
                    $ap = $ra;

            if ( isset( $ap ) === false )
                foreach ( $rp->getAttributes() as $ra )
                    if ( $ra->getName() === JoinColumn::class )
                        $ap = $ra;


            foreach ( $reflectionAttributes as $reflectionAttribute ) {
                $validationConstraint = new ( $reflectionAttribute->getName() )(
                    ...$reflectionAttribute->getArguments()
                );

                if ( isset( $this->$pName ) ) {
                    if ( $validationConstraint instanceof AbstractConstraint === false )
                        continue;

                    $validationConstraint->setPropertyName( $pName );

                    $validator = new ( $reflectionAttribute->getName() . 'Validator' )(
                        $this->$pName, $validationConstraint, $this
                    );
                    assert( $validator instanceof AbstractConstraintValidator );

                    $validator->validate();
                    if ( ( $err = $validator->getError() ) !== false )
                        $this->validationErrors[ $pName ] = $err;

                } elseif ( $ap !== null ) {
                    if ( array_key_exists( 'nullable', $ap->getArguments() ) ) {
                        if ( $ap->getArguments()['nullable'] === false )
                            $this->validationErrors[ $pName ] =
                                _t(
                                    'Field `%s` cannot be null.',
                                    _t( $this->getTranslatablePropertyName( $pName ) )
                                );

                    } else
                        $this->validationErrors[ $pName ] =
                            _t(
                                'Field `%s` cannot be null.',
                                _t( $this->getTranslatablePropertyName( $pName ) )
                            );

                    return $this->getValidationErrors();
                }
            }
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
