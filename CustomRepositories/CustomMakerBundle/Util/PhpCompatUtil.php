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

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Bundle\MakerBundle\FileManager;
use const PHP_VERSION;

class PhpCompatUtil
{
    private FileManager $fileManager;

    public function __construct( FileManager $fileManager )
    {
        $this->fileManager = $fileManager;
    }

    public function canUseAttributes(): bool
    {
        $version = $this->getPhpVersion();

        return version_compare( $version, '8alpha', '>=' ) && Kernel::VERSION_ID >= 50200;
    }

    protected function getPhpVersion(): string
    {
        $rootDirectory = $this->fileManager->getRootDirectory();

        $composerLockPath = sprintf( '%s/composer.lock', $rootDirectory );

        if ( !$this->fileManager->fileExists( $composerLockPath ) )
            return PHP_VERSION;


        $lockFileContents = j_decode( $this->fileManager->getFileContents( $composerLockPath ), true );

        if ( empty( $lockFileContents['platform-overrides'] ) ||
             empty( $lockFileContents['platform-overrides']['php'] ) )
            return PHP_VERSION;

        return $lockFileContents['platform-overrides']['php'];
    }

    public function canUseTypedProperties(): bool
    {
        $version = $this->getPhpVersion();

        return version_compare( $version, '7.4', '>=' );
    }

    public function canUseUnionTypes(): bool
    {
        $version = $this->getPhpVersion();

        return version_compare( $version, '8alpha', '>=' );
    }
}
