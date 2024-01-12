<?php /** @noinspection MissingReturnTypeInspection @noinspection ReturnTypeCanBeDeclaredInspection @noinspection PhpMissingReturnTypeInspection */
declare( strict_types=1 );

namespace Doctrine\DBAL\Platforms;

use Doctrine\Deprecations\Deprecation;

/**
 * Provides the behavior, features and SQL dialect of the MySQL 8.0 (8.0 GA) database platform.
 */
final class MySQL80Platform extends MySQL57Platform
{
    /**
     * {@inheritdoc}
     *
     * @deprecated Implement {@see createReservedKeywordsList()} instead.
     */
    protected function getReservedKeywordsClass()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4510',
            'MySQL80Platform::getReservedKeywordsClass() is deprecated, use MySQL80Platform::createReservedKeywordsList() instead.',
        );

        return Keywords\MySQL80Keywords::class;
    }

    /**
     * @param string $sequence
     *
     * @return string
     */
    public function getSequenceNextValSQL( $sequence )
    {
        $tableName = str_replace( '_id_seq', '', $sequence );
        $tableSchema = explode( '?', explode( '3306/', env( 'DATABASE_URL' ) )[1])[0];

        /** @noinspection SqlResolve */
        return "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_NAME = '$tableName' AND TABLE_SCHEMA = '$tableSchema'";
    }

}
