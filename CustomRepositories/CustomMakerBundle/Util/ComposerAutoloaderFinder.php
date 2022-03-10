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

use RuntimeException;
use JetBrains\PhpStorm\Pure;
use Composer\Autoload\ClassLoader;
use Symfony\Component\Debug\DebugClassLoader;
use Symfony\Component\ErrorHandler\DebugClassLoader as ErrorHandlerDebugClassLoader;
use function is_array;
use function is_object;

class ComposerAutoloaderFinder
{
    private array $rootNamespace;

    private ?ClassLoader $classLoader = null;

    public function __construct( string $rootNamespace )
    {
        $this->rootNamespace = [
            'psr0' => rtrim( $rootNamespace, '\\' ),
            'psr4' => rtrim( $rootNamespace, '\\' ) . '\\',
        ];
    }

    public function getClassLoader(): ClassLoader
    {
        if ( $this->classLoader === null )
            $this->classLoader = $this->findComposerClassLoader();


        if ( $this->classLoader === null )
            throw new RuntimeException(
                "Could not find a Composer autoloader that autoloads from '{$this->rootNamespace['psr4']}'"
            );


        return $this->classLoader;
    }

    private function findComposerClassLoader(): ?ClassLoader
    {
        $autoloadFunctions = spl_autoload_functions();

        foreach ( $autoloadFunctions as $autoloader ) {
            if ( !is_array( $autoloader ) )
                continue;


            $classLoader = $this->extractComposerClassLoader( $autoloader );
            if ( $classLoader === null )
                continue;


            if ( ( $finalClassLoader = $this->locateMatchingClassLoader( $classLoader ) ) !== null )
                return $finalClassLoader;

        }

        return null;
    }

    #[Pure]
    private function extractComposerClassLoader( array $autoloader ): ?ClassLoader
    {
        if ( isset( $autoloader[0] ) && is_object( $autoloader[0] ) ) {
            if ( $autoloader[0] instanceof ClassLoader )
                return $autoloader[0];

            if ( (
                     $autoloader[0] instanceof DebugClassLoader ||
                     $autoloader[0] instanceof ErrorHandlerDebugClassLoader
                 ) &&
                 is_array( $autoloader[0]->getClassLoader() ) &&
                 $autoloader[0]->getClassLoader()[0] instanceof ClassLoader
            )
                return $autoloader[0]->getClassLoader()[0];

        }

        return null;
    }

    private function locateMatchingClassLoader( ClassLoader $classLoader ): ?ClassLoader
    {
        $makerClassLoader = null;
        foreach ( $classLoader->getPrefixesPsr4() as $prefix => $paths ) {
            if ( $prefix === 'Symfony\\Bundle\\MakerBundle\\' )
                $makerClassLoader = $classLoader;

            if ( str_starts_with( $this->rootNamespace['psr4'], $prefix ) )
                return $classLoader;

        }

        foreach ( $classLoader->getPrefixes() as $prefix => $paths )
            if ( str_starts_with( $this->rootNamespace['psr0'], $prefix ) )
                return $classLoader;

        // Nothing found? Try the class loader where we found MakerBundle
        return $makerClassLoader;
    }
}
