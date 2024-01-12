<?php declare( strict_types=1 );

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Platform/vendor/autoload.php';

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Id\SequenceGenerator;
use Doctrine\ORM\Query;

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

        return $result;
    }

}

ClearRedundantClassesCommand::clear();