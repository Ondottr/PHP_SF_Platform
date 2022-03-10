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

namespace Symfony\Bundle\MakerBundle;

use RuntimeException;
use JetBrains\PhpStorm\Pure;
use InvalidArgumentException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Bundle\MakerBundle\Util\AutoloaderUtil;
use Symfony\Bundle\MakerBundle\Util\MakerFileLinkFormatter;
use const EXTR_SKIP;

class FileManager
{
    private Filesystem     $fs;
    private AutoloaderUtil $autoloaderUtil;
    private MakerFileLinkFormatter $makerFileLinkFormatter;
    private string                 $rootDirectory;
    private SymfonyStyle $io;
    private ?string      $twigDefaultPath;

    public function __construct(
        Filesystem             $fs,
        AutoloaderUtil         $autoloaderUtil,
        MakerFileLinkFormatter $makerFileLinkFormatter,
        string                 $rootDirectory,
        string                 $twigDefaultPath = null
    ) {
        // move FileManagerTest stuff
        // update EntityRegeneratorTest to mock the autoloader
        $this->fs                     = $fs;
        $this->autoloaderUtil         = $autoloaderUtil;
        $this->makerFileLinkFormatter = $makerFileLinkFormatter;
        $this->rootDirectory          = rtrim( $this->realPath( $this->normalizeSlashes( $rootDirectory ) ), '/' );
        $this->twigDefaultPath        = $twigDefaultPath ? rtrim( $this->relativizePath( $twigDefaultPath ), '/' )
            : null;
    }

    public function setIO( SymfonyStyle $io ): void
    {
        $this->io = $io;
    }

    public function parseTemplate( string $templatePath, array $parameters ): string
    {
        ob_start();
        extract( $parameters, EXTR_SKIP );
        include $templatePath;

        return ob_get_clean();
    }

    public function dumpFile( string $filename, string $content ): void
    {
        $absolutePath    = $this->absolutizePath( $filename );
        $newFile         = !$this->fileExists( $filename );
        $existingContent = $newFile ? '' : file_get_contents( $absolutePath );

        $comment = $newFile ? '<fg=blue>created</>' : '<fg=yellow>updated</>';
        if ( $existingContent === $content ) {
            $comment = '<fg=green>no change</>';
        }

        $this->fs->dumpFile( $absolutePath, $content );
        $relativePath = $this->relativizePath( $filename );

        $this->io->comment(
            sprintf(
                '%s: %s',
                $comment,
                $this->makerFileLinkFormatter->makeLinkedPath( $absolutePath, $relativePath )
            )
        );
    }

    #[Pure]
    public function fileExists( $path ): bool
    {
        return file_exists( $this->absolutizePath( $path ) );
    }

    /**
     * Attempts to make the path relative to the root directory.
     */
    public function relativizePath( string $absolutePath ): string
    {
        $absolutePath = $this->normalizeSlashes( $absolutePath );

        // see if the path is even in the root
        if ( !str_contains( $absolutePath, $this->rootDirectory ) )
            return $absolutePath;


        $absolutePath = $this->realPath( $absolutePath );

        // str_replace but only the first occurrence
        $relativePath = ltrim( implode( '', explode( $this->rootDirectory, $absolutePath, 2 ) ), '/' );
        if ( strncmp( $relativePath, './', 2 ) === 0 )
            $relativePath = substr( $relativePath, 2 );

        return is_dir( $absolutePath ) ? rtrim( $relativePath, '/' ) . '/' : $relativePath;
    }

    public function getFileContents( string $path ): string
    {
        if ( !$this->fileExists( $path ) )
            throw new InvalidArgumentException( sprintf( 'Cannot find file "%s"', $path ) );

        return file_get_contents( $this->absolutizePath( $path ) );
    }

    public function createFinder( string $in ): Finder
    {
        $finder = new Finder();
        $finder->in( $this->absolutizePath( $in ) );

        return $finder;
    }

    public function isPathInVendor( string $path ): bool
    {
        return str_starts_with(
            $this->normalizeSlashes( $path ),
            $this->normalizeSlashes( $this->rootDirectory . '/vendor/' )
        );
    }

    public function absolutizePath( $path ): string
    {
        if ( strncmp( $path, '/', 1 ) === 0 )
            return $path;


        // support windows drive paths: C:\ or C:/
        if ( strpos( $path, ':\\' ) === 1 || strpos( $path, ':/' ) === 1 )
            return $path;

        return sprintf( '%s/%s', $this->rootDirectory, $path );
    }

    public function getRelativePathForFutureClass( string $className ): ?string
    {
        $path = $this->autoloaderUtil->getPathForFutureClass( $className );

        return $path === null ? null : $this->relativizePath( $path );
    }

    public function getNamespacePrefixForClass( string $className ): string
    {
        return $this->autoloaderUtil->getNamespacePrefixForClass( $className );
    }

    public function isNamespaceConfiguredToAutoload( string $namespace ): bool
    {
        return $this->autoloaderUtil->isNamespaceConfiguredToAutoload( $namespace );
    }

    public function getRootDirectory(): string
    {
        return $this->rootDirectory;
    }

    public function getPathForTemplate( string $filename ): string
    {
        if ( $this->twigDefaultPath === null )
            throw new RuntimeException( 'Cannot get path for template: is Twig installed?' );

        return $this->twigDefaultPath . '/' . $filename;
    }

    /**
     * Resolve '../' in paths (like real_path), but for non-existent files.
     */
    private function realPath( string $absolutePath ): string
    {
        $finalParts   = [];
        $currentIndex = -1;

        $absolutePath = $this->normalizeSlashes( $absolutePath );
        foreach ( explode( '/', $absolutePath ) as $pathPart ) {
            if ( $pathPart === '..' ) {
                // we need to remove the previous entry
                if ( $currentIndex === -1 )
                    throw new RuntimeException(
                        sprintf( 'Problem making path relative - is the path "%s" absolute?', $absolutePath )
                    );

                unset( $finalParts[ $currentIndex ] );
                --$currentIndex;

                continue;
            }

            $finalParts[] = $pathPart;
            ++$currentIndex;
        }

        // Normalize: // => /
        // Normalize: /./ => /
        return str_replace( [ '//', '/./' ], '/', implode( '/', $finalParts ) );
    }

    private function normalizeSlashes( string $path )
    {
        return str_replace( '\\', '/', $path );
    }
}
