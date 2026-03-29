<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

abstract class AbstractConstraint
{
    private string $propertyName;

    final public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    final public function setPropertyName( string $propertyName ): void
    {
        $this->propertyName = $propertyName;
    }
}
