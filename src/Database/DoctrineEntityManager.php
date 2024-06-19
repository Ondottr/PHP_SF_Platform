<?php /** @noinspection ProhibitedClassExtendInspection @noinspection PhpMissingParentCallCommonInspection */
declare( strict_types=1 );
/*
 * Copyright © 2018-2024, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace PHP_SF\System\Database;

use Doctrine\DBAL as DBAL;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Query;
use Exception;
use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHP_SF\System\Database\Doctrine\QuoteStrategy;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class DoctrineEntityManager extends EntityManager
{

    /**
     * List of supported drivers and their mappings to the driver classes.
     *
     * To add your own driver use the 'driverClass' parameter to {@see DBAL\DriverManager::getConnection()}.
     *
     * @see DBAL\DriverManager::DRIVER_MAP const
     */
    private const DRIVER_MAP = [
        'mysql' => 'pdo_mysql',
        'sqlite' => 'pdo_sqlite',
        'postgresql' => 'pdo_pgsql',
    ];


    private static self|null $entityManager = null;
    /**
     * @var array<string>
     */
    private static array $entityDirectories = [];

    public static function invalidateEntityManager(): void
    {
        self::$entityManager = null;
    }

    public static function getEntityManager(): self
    {
        if ( self::$entityManager === null )
            self::setEntityManager();

        return self::$entityManager;
    }

    private static function setEntityManager(): void
    {
        $ra = new RedisAdapter( Redis::getClient() );
        $config = ORMSetup::createAttributeMetadataConfiguration(
            self::getEntityDirectories(), DEV_MODE,
            __DIR__ . '/../../../var/cache/prod/doctrine/orm/Proxies',
            $ra
        );

        $config->setProxyNamespace( 'Proxies' );

        $config->setMetadataCache( $ra );
        $config->setHydrationCache( $ra );

        $config->setQuoteStrategy(new QuoteStrategy);

        if ( $config->getMetadataDriverImpl() === false )
            throw MissingMappingDriverImplementation::create();

        // parse params from url
        $params = parse_url( env( 'DATABASE_URL' ) );
        $params['driver'] = self::DRIVER_MAP[self::detectDriverByDBUrl(env('DATABASE_URL'))];
        $params['password'] = $params['pass'];
        $params['dbname']   = ltrim( $params['path'], '/' );

        $connection = DBAL\DriverManager::getConnection( $params, $config );

        self::$entityManager = new self( $connection, $config );
    }

    public static function getEntityDirectories(): array
    {
        return self::$entityDirectories;
    }

    public static function addEntityDirectory( string $entityDirectories ): void
    {
        self::$entityDirectories[] = $entityDirectories;
    }

    public function createQuery($dql = '' ): Query
    {
        $query = new Query( $this );

        if ( empty( $dql ) === false )
            $query->setDQL( $dql );

        return $query;
    }

    final public function executeSeveral( Query ...$queries ): bool
    {
        try {
            em()->beginTransaction();

            foreach ( $queries as $query ) {
                $query->execute();
            }

            em()->commit();
        } catch ( Exception $e ) {
            em()->rollBack();

            throw $e;
        }

        return true;
    }

    final public function flushUsingTransaction( AbstractEntity ...$entities ): void
    {
        try {
            em()->beginTransaction();

            if ( empty( $entities ) === false ) {
                foreach ( $entities as $entity )
                    em()->flush( $entity );

            } else
                em()->flush();

            em()->commit();
        } catch ( Exception $e ) {
            em()->rollback();

            throw $e;
        }
    }

    /**
     * @param AbstractEntity|null $entity
     */
    final public function flush( $entity = null ): void
    {
        parent::flush( $entity );
    }


    private static function detectDriverByDBUrl(?string $env)
    {
        if (!$env) {
            throw new InvalidConfigurationException('DATABASE_URL environment variable is not defined.');
        }

        // Extract the driver from the DATABASE_URL
        $urlParts = parse_url($env);
        if (isset($urlParts['scheme'])) {
            $driver = str_replace('pdo_', '', $urlParts['scheme']); // Remove 'pdo_' prefix if present
            if (array_key_exists($driver, self::DRIVER_MAP)) {
                return $driver;
            }
        }

        // throw exception if the driver is not found or the URL is not valid
        throw new InvalidConfigurationException(
            sprintf('The given driver "%s" is not supported, or could not parse the url "%s".', $driver ?? '', $env)
        );
    }


}
