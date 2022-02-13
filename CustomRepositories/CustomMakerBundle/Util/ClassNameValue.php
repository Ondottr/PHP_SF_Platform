<?php

namespace Symfony\Bundle\MakerBundle\Util;

use Symfony\Bundle\MakerBundle\Str;

final class ClassNameValue
{
    private $typeHint;
    private $fullClassName;

    public function __construct( string $typeHint, string $fullClassName )
    {
        $this->typeHint      = $typeHint;
        $this->fullClassName = $fullClassName;
    }

    public function getShortName(): string
    {
        if ( $this->isSelf() ) {
            return Str::getShortClassName( $this->fullClassName );
        }

        return $this->typeHint;
    }

    public function isSelf(): bool
    {
        return 'self' === $this->typeHint;
    }

    public function __toString()
    {
        return $this->getShortName();
    }
}
