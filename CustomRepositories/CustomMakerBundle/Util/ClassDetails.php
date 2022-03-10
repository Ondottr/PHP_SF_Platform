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

namespace Symfony\Bundle\MakerBundle\Util;

use ReflectionClass;

final class ClassDetails
{
    private string $fullClassName;

    public function __construct( string $fullClassName )
    {
        $this->fullClassName = $fullClassName;
    }

    /**
     * Get list of property names except "id" for use in a make:form context.
     */
    public function getFormFields(): array
    {
        $properties = $this->getProperties();

        $fields = array_diff( $properties, [ 'id' ] );

        $fieldsWithTypes = [];
        foreach ( $fields as $field ) {
            $fieldsWithTypes[ $field ] = null;
        }

        return $fieldsWithTypes;
    }

    private function getProperties(): array
    {
        $reflect = new ReflectionClass( $this->fullClassName );
        $props   = $reflect->getProperties();

        $propertiesList = [];

        foreach ( $props as $prop ) {
            $propertiesList[] = $prop->getName();
        }

        return $propertiesList;
    }

    public function getPath(): string
    {
        return ( new ReflectionClass( $this->fullClassName ) )->getFileName();
    }

    /**
     * An imperfect, but simple way to check for the presence of an annotation.
     *
     * @param string $annotation The annotation - e.g. @UniqueEntity
     */
    public function doesDocBlockContainAnnotation( string $annotation ): bool
    {
        $docComment = ( new ReflectionClass( $this->fullClassName ) )->getDocComment();

        if ( $docComment === false )
            return false;


        return str_contains( $docComment, $annotation );
    }
}
