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

final class TemplateComponentGenerator
{
    private PhpCompatUtil $phpCompatUtil;

    public function __construct( PhpCompatUtil $phpCompatUtil )
    {
        $this->phpCompatUtil = $phpCompatUtil;
    }

    public static function generateUseStatements( array $classesToBeImported ): string
    {
        $transformed = [];

        foreach ( $classesToBeImported as $key => $class )
            $transformed[ $key ] = str_replace( '\\', ' ', $class );

        asort( $transformed );

        $statements = '';

        foreach ( $transformed as $key => $class )
            $statements .= sprintf( "use %s;\n", $classesToBeImported[ $key ] );

        return $statements;
    }

    /** @legacy Annotation Support can be dropped w/ Symfony 6 LTS */
    public function generateRouteForControllerMethod(
        string $routePath,
        string $routeName,
        array  $methods = [],
        bool   $indent = true,
        bool   $trailingNewLine = true
    ): string {
        if ( $this->phpCompatUtil->canUseAttributes() ) {
            $attribute = sprintf( '%s#[Route(\'%s\', name: \'%s\'', $indent ? '    ' : null, $routePath, $routeName );

            if ( !empty( $methods ) ) {
                $attribute .= ', methods: [';

                foreach ( $methods as $method )
                    $attribute .= sprintf( '\'%s\', ', $method );

                $attribute = rtrim( $attribute, ', ' );

                $attribute .= ']';
            }

            $attribute .= sprintf( ')]%s', $trailingNewLine ? "\n" : null );

            return $attribute;
        }

        $annotation = sprintf( '%s/**%s', $indent ? '    ' : null, "\n" );
        $annotation .= sprintf( '%s * @Route("%s", name="%s"', $indent ? '    ' : null, $routePath, $routeName );

        if ( !empty( $methods ) ) {
            $annotation .= ', methods={';

            foreach ( $methods as $method )
                $annotation .= sprintf( '"%s", ', $method );

            $annotation = rtrim( $annotation, ', ' );

            $annotation .= '}';
        }

        $annotation .= sprintf( ')%s', "\n" );
        $annotation .= sprintf( '%s */%s', $indent ? '    ' : null, $trailingNewLine ? "\n" : null );

        return $annotation;
    }

    public function getPropertyType( ClassNameDetails $classNameDetails ): ?string
    {
        if ( !$this->phpCompatUtil->canUseTypedProperties() )
            return null;

        return sprintf( '%s ', $classNameDetails->getShortName() );
    }

    public function repositoryHasAddRemoveMethods( string $repositoryFullClassName ): bool
    {
        $reflectedComponents = new ReflectionClass( $repositoryFullClassName );

        return $reflectedComponents->hasMethod( 'add' ) && $reflectedComponents->hasMethod( 'remove' );
    }
}
