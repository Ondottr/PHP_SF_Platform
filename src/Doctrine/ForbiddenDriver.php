<?php declare( strict_types=1 );

namespace PHP_SF\System\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\ServerVersionProvider;
use LogicException;
use SensitiveParameter;

/**
 * A deliberately non-functional Doctrine DBAL driver.
 *
 * Used as the default connection driver to prevent accidental usage of an
 * unnamed database connection. Every method throws a LogicException with a
 * clear, actionable message pointing developers to the correct named connection.
 */
final class ForbiddenDriver implements Driver
{

    private const ERROR_MESSAGE =
        'Attempted to use the default (dummy) Doctrine connection. ' .
        'You MUST explicitly use a named entity manager: em(\'name\') or --em=name. ' .
        'See config/packages/doctrine.yaml for configured connections.';


    public function connect( #[SensitiveParameter] array $params ): DriverConnection
    {
        throw new LogicException( self::ERROR_MESSAGE );
    }

    public function getDatabasePlatform( ServerVersionProvider $versionProvider ): AbstractPlatform
    {
        // Must return a valid platform so Doctrine ORM can initialize entity metadata
        // and generate proxy classes without connecting. Actual connections still
        // throw via connect().
        return new SQLitePlatform();
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        throw new LogicException( self::ERROR_MESSAGE );
    }

}
