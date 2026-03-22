<?php declare( strict_types=1 );

namespace PHP_SF\System\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use LogicException;
use SensitiveParameter;

/**
 * A deliberately non-functional Doctrine DBAL driver.
 *
 * Used as the default connection driver to prevent accidental usage of an
 * unnamed database connection. Every method throws a LogicException with a
 * clear, actionable message pointing developers to the correct named connection.
 *
 * @author Dmytro Dyvulskyi <dmytro.dyvulskyi@medserv.ie>
 */
final class ForbiddenDriver implements Driver
{

    private const ERROR_MESSAGE =
        'Attempted to use the default (dummy) Doctrine connection. ' .
        'You MUST explicitly use a named connection or entity manager: ' .
        'invoices_uk, insurance_uk, billing_uk, etc. ' .
        'See config/packages/doctrine.yaml for details.';


    public function connect( #[SensitiveParameter] array $params ): DriverConnection
    {
        throw new LogicException( self::ERROR_MESSAGE );
    }

    public function getDatabasePlatform( ServerVersionProvider $versionProvider ): AbstractPlatform
    {
        throw new LogicException( self::ERROR_MESSAGE );
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        throw new LogicException( self::ERROR_MESSAGE );
    }

}
