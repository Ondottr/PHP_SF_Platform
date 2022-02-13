<?php

namespace Symfony\Bundle\MakerBundle\Doctrine;

abstract class BaseRelation
{
    private string  $propertyName;
    private string  $targetClassName;
    private ?string $targetPropertyName;
    private ?string $customReturnType;
    private bool    $isSelfReferencing  = false;
    private bool    $mapInverseRelation = true;
    private bool    $avoidSetter        = false;

    abstract public function isOwning(): bool;

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    public function setPropertyName( string $propertyName ): self
    {
        $this->propertyName = $propertyName;

        return $this;
    }

    public function getTargetClassName(): string
    {
        return $this->targetClassName;
    }

    public function setTargetClassName( string $targetClassName ): self
    {
        $this->targetClassName = $targetClassName;

        return $this;
    }

    public function getTargetPropertyName(): ?string
    {
        return $this->targetPropertyName;
    }

    public function setTargetPropertyName( ?string $targetPropertyName ): self
    {
        $this->targetPropertyName = $targetPropertyName;

        return $this;
    }

    public function isSelfReferencing(): bool
    {
        return $this->isSelfReferencing;
    }

    public function setIsSelfReferencing( bool $isSelfReferencing ): self
    {
        $this->isSelfReferencing = $isSelfReferencing;

        return $this;
    }

    public function getMapInverseRelation(): bool
    {
        return $this->mapInverseRelation;
    }

    public function setMapInverseRelation( bool $mapInverseRelation ): self
    {
        $this->mapInverseRelation = $mapInverseRelation;

        return $this;
    }

    public function shouldAvoidSetter(): bool
    {
        return $this->avoidSetter;
    }

    public function avoidSetter( bool $avoidSetter = true ): self
    {
        $this->avoidSetter = $avoidSetter;

        return $this;
    }

    public function getCustomReturnType(): ?string
    {
        return $this->customReturnType;
    }

    public function isCustomReturnTypeNullable(): bool
    {
        return $this->isCustomReturnTypeNullable;
    }

    public function setCustomReturnType( string $customReturnType, bool $isNullable ): BaseRelation
    {
        $this->customReturnType           = $customReturnType;
        $this->isCustomReturnTypeNullable = $isNullable;

        return $this;
    }
}
