<?php declare( strict_types=1 );

namespace PHP_SF\Framework\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Id\SequenceGenerator;
use Doctrine\ORM\Query;
use ReflectionClass;
use RuntimeException;

class ClearRedundantClassesCommand
{
    private const customRepositoryClasses = [
        Query::class,
        Connection::class,
        AbstractQuery::class,
        MySQL80Platform::class,
        SequenceGenerator::class,
    ];

    public static function clear(): bool
    {
        if ( ra()->has( 'redundant_classes_cleared' ) )
            return true;

        $filesToDelete = [];
        foreach ( self::customRepositoryClasses as $class ) {
            $rc = new ReflectionClass( $class );

            if ( str_contains( $rc->getFileName(), '/vendor/' ) && file_exists( $rc->getFileName() ) )
                $filesToDelete[] = $rc->getFileName();

        }

        $result = true;
        foreach ( $filesToDelete as $file )
            $result = $result && unlink( $file );

        if ( $result === false && empty( $filesToDelete ) === false )
            throw new RuntimeException( sprintf( 'Something went wrong while trying to delete redundant files! ' .
                'Some files were not deleted! Delete these files manually: "%s"!', implode( '" "', $filesToDelete )
            ) );

        ra()->set( 'redundant_classes_cleared', true );

        return $result;
    }

}