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

use Composer\Autoload\ClassLoader;
use function strlen;

class AutoloaderUtil
{
    private ComposerAutoloaderFinder $autoloaderFinder;

    public function __construct( ComposerAutoloaderFinder $autoloaderFinder )
    {
        $this->autoloaderFinder = $autoloaderFinder;
    }

    /**
     * Returns the relative path to where a new class should live.
     *
     * @throws \Exception
     */
    public function getPathForFutureClass( string $className ): ?string
    {
        $classLoader = $this->getClassLoader();

        // lookup is obviously modeled off of Composer's autoload logic
        foreach ( $classLoader->getPrefixesPsr4() as $prefix => $paths )
            if ( str_starts_with( $className, $prefix ) )
                return $paths[0] . '/' . str_replace( '\\', '/', substr( $className, strlen( $prefix ) ) ) . '.php';


        foreach ( $classLoader->getPrefixes() as $prefix => $paths )
            if ( str_starts_with( $className, $prefix ) )
                return $paths[0] . '/' . str_replace( '\\', '/', $className ) . '.php';


        if ( $classLoader->getFallbackDirsPsr4() )
            return $classLoader->getFallbackDirsPsr4()[0] . '/' . str_replace( '\\', '/', $className ) . '.php';


        if ( $classLoader->getFallbackDirs() )
            return $classLoader->getFallbackDirs()[0] . '/' . str_replace( '\\', '/', $className ) . '.php';


        return null;
    }

    private function getClassLoader(): ClassLoader
    {
        return $this->autoloaderFinder->getClassLoader();
    }

    public function getNamespacePrefixForClass( string $className ): string
    {
        foreach ( $this->getClassLoader()->getPrefixesPsr4() as $prefix => $paths )
            if ( str_starts_with( $className, $prefix ) )
                return $prefix;

        return '';
    }

    /**
     * Returns if the namespace is configured by composer autoloader.
     */
    public function isNamespaceConfiguredToAutoload( string $namespace ): bool
    {
        $namespace   = trim( $namespace, '\\' ) . '\\';
        $classLoader = $this->getClassLoader();

        foreach ( $classLoader->getPrefixesPsr4() as $prefix => $paths )
            if ( str_starts_with( $namespace, $prefix ) )
                return true;

        foreach ( $classLoader->getPrefixes() as $prefix => $paths )
            if ( str_starts_with( $namespace, $prefix ) )
                return true;

        return false;
    }
}
