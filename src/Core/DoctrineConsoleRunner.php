<?php declare( strict_types=1 );

/**
 *  Copyright © 2018-2023, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 *  Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 *  granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 *  THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 *  INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 *  LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 *  RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 *  TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace PHP_SF\System\Core;

use OutOfBoundsException;
use PackageVersions\Versions;
use Doctrine\ORM\Tools\Console\Command;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Application;
use Doctrine\DBAL\Tools\Console as DBALConsole;
use Symfony\Component\Console\Helper\HelperSet;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\HelperSetManagerProvider;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\ConnectionFromManagerProvider;

/**
 * Handles running the Console Tools inside Symfony Console context.
 */
final class DoctrineConsoleRunner
{

    /**
     * Create a Symfony Console HelperSet
     */
    public static function createHelperSet(EntityManagerInterface $entityManager): HelperSet
    {
        return new HelperSet(
            [
                'db' => new DBALConsole\Helper\ConnectionHelper($entityManager->getConnection()),
                'em' => new EntityManagerHelper($entityManager),
            ]
        );
    }

    /**
     * Runs console with the given helper set.
     *
     * @param HelperSet|EntityManagerProvider $helperSetOrProvider
     * @param SymfonyCommand[]                $commands
     */
    public static function run($helperSetOrProvider, array $commands = []): void
    {
        $cli = self::createApplication($helperSetOrProvider, $commands);
        $cli->run();
    }

    /**
     * Creates a console application with the given helperset and
     * optional commands.
     *
     * @param HelperSet|EntityManagerProvider $helperSetOrProvider
     * @param SymfonyCommand[]                $commands
     *
     * @throws OutOfBoundsException
     */
    public static function createApplication($helperSetOrProvider, array $commands = []): Application
    {
        $cli = new Application('Doctrine Command Line Interface', Versions::getVersion('doctrine/orm'));
        $cli->setCatchExceptions(true);

        if ($helperSetOrProvider instanceof HelperSet) {
            $cli->setHelperSet($helperSetOrProvider);

            $helperSetOrProvider = new HelperSetManagerProvider($helperSetOrProvider);
        }

        self::addCommands($cli, $helperSetOrProvider);
        $cli->addCommands($commands);

        return $cli;
    }

    public static function addCommands(Application $cli, ?EntityManagerProvider $entityManagerProvider = null): void
    {
        if ($entityManagerProvider === null) {
            $entityManagerProvider = new HelperSetManagerProvider($cli->getHelperSet());
        }

        $connectionProvider = new ConnectionFromManagerProvider($entityManagerProvider);

        $cli->addCommands(
            [
                // DBAL Commands
                new DBALConsole\Command\ImportCommand(),
                new DBALConsole\Command\ReservedWordsCommand($connectionProvider),
                new DBALConsole\Command\RunSqlCommand($connectionProvider),

                // ORM Commands
                new Command\ClearCache\CollectionRegionCommand($entityManagerProvider),
                new Command\ClearCache\EntityRegionCommand($entityManagerProvider),
                new Command\ClearCache\MetadataCommand($entityManagerProvider),
                new Command\ClearCache\QueryCommand($entityManagerProvider),
                new Command\ClearCache\QueryRegionCommand($entityManagerProvider),
                new Command\ClearCache\ResultCommand($entityManagerProvider),
                new Command\SchemaTool\CreateCommand($entityManagerProvider),
                new Command\SchemaTool\UpdateCommand($entityManagerProvider),
                new Command\SchemaTool\DropCommand($entityManagerProvider),
                new Command\EnsureProductionSettingsCommand($entityManagerProvider),
                new Command\ConvertDoctrine1SchemaCommand(),
                new Command\GenerateRepositoriesCommand($entityManagerProvider),
                new Command\GenerateEntitiesCommand($entityManagerProvider),
                new Command\GenerateProxiesCommand($entityManagerProvider),
                new Command\ConvertMappingCommand($entityManagerProvider),
                new Command\RunDqlCommand($entityManagerProvider),
                new Command\ValidateSchemaCommand($entityManagerProvider),
                new Command\InfoCommand($entityManagerProvider),
                new Command\MappingDescribeCommand($entityManagerProvider),
            ]
        );
    }

    public static function printCliConfigTemplate(): void
    {
        echo <<<'HELP'
You are missing a "cli-config.php" or "config/cli-config.php" file in your
project, which is required to get the Doctrine Console working. You can use the
following sample as a template:

<?php
use Doctrine\ORM\Tools\Console\ConsoleRunner;

// replace with file to your own project bootstrap
require_once 'bootstrap.php';

// replace with mechanism to retrieve EntityManager in your app
$entityManager = GetEntityManager();

return ConsoleRunner::createHelperSet($entityManager);

HELP;
    }
}
