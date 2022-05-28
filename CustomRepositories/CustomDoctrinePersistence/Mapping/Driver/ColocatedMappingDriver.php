<?php declare( strict_types=1 );

namespace Doctrine\Persistence\Mapping\Driver;

use RegexIterator;
use ReflectionClass;
use FilesystemIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Doctrine\Persistence\Mapping\MappingException;
use function assert;
use function is_dir;
use function in_array;
use function realpath;
use function preg_match;
use function preg_quote;
use function str_replace;
use function get_declared_classes;

/**
 * The CollocatedMappingDriver reads the mapping metadata located near the code.
 */
trait ColocatedMappingDriver
{

    /**
     * Appends lookup paths to metadata driver.
     *
     * @param array<int, string> $paths
     *
     * @return void
     */
    public function addPaths( array $paths ): void
    {
        $this->paths = $paths;
    }

    /**
     * Retrieves the defined metadata lookup paths.
     *
     * @return array<int, string>
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * Append exclude lookup paths to metadata driver.
     *
     * @param string[] $paths
     *
     * @return void
     */
    public function addExcludePaths( array $paths ): void
    {
        $this->excludePaths = $paths;
    }

    /**
     * Retrieve the defined metadata lookup exclude paths.
     *
     * @return array<int, string>
     */
    public function getExcludePaths()
    {
        return $this->excludePaths;
    }

    /**
     * Gets the file extension used to look for mapping files under.
     *
     * @return string
     */
    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }

    /**
     * Sets the file extension used to look for mapping files under.
     *
     * @param $fileExtension
     *
     * @return void
     */
    public function setFileExtension( $fileExtension ): void
    {
        $this->fileExtension = $fileExtension;
    }

    /**
     * {@inheritDoc}
     *
     * Returns whether the class with the specified name is transient. Only non-transient
     * classes, that is entities and mapped superclasses, should have their metadata loaded.
     *
     * @param string             $className
     *
     * @psalm-param class-string $className
     *
     * @return bool
     */
    abstract public function isTransient( $className );

    /**
     * Gets the names of all mapped classes known to this driver.
     *
     * @return string[] The names of all mapped classes known to this driver.
     * @psalm-return list<class-string>
     *
     * @throws \Doctrine\Persistence\Mapping\MappingException
     * @throws \ReflectionException
     */
    public function getAllClassNames(): array
    {
        if ( isset( $this->classNames ) && $this->classNames !== null )
            return $this->classNames;

        if ( $this->paths === [] )
            throw MappingException::pathRequiredForDriver( static::class );

        $classes       = [];
        $includedFiles = [];

        foreach ( $this->paths as $path ) {
            if ( !is_dir( $path ) )
                throw MappingException::fileMappingDriversRequireConfiguredDirectoryPath( $path );

            $iterator = new RegexIterator(
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
                    RecursiveIteratorIterator::LEAVES_ONLY
                ),
                '/^.+' . preg_quote( $this->fileExtension ?? '' ) . '$/i',
                RegexIterator::GET_MATCH
            );

            foreach ( $iterator as $file ) {
                $sourceFile = $file[0];

                if ( preg_match( '(^phar:)i', $sourceFile ) === 0 )
                    $sourceFile = realpath( $sourceFile );

                if ( isset( $this->excludePaths ) ) {
                    foreach ( $this->excludePaths as $excludePath ) {
                        $realExcludePath = realpath( $excludePath );
                        assert( $realExcludePath !== false );
                        $exclude = str_replace( '\\', '/', $realExcludePath );
                        $current = str_replace( '\\', '/', $sourceFile );

                        if ( str_contains( $current, $exclude ) )
                            continue 2;

                    }
                }

                require_once $sourceFile;

                $includedFiles[] = $sourceFile;
            }
        }

        $declared = get_declared_classes();

        foreach ( $declared as $className ) {
            $rc = new ReflectionClass( $className );

            $sourceFile = $rc->getFileName();

            if ( !in_array( $sourceFile, $includedFiles, true ) || $this->isTransient( $className ) ) {
                continue;
            }

            $classes[] = $className;
        }

        $this->classNames = $classes;

        return $classes;
    }
}
