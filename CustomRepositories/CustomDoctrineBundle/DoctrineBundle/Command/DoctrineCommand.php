<?php declare( strict_types=1 );

namespace Doctrine\Bundle\DoctrineBundle\Command;

use LogicException;
use Doctrine\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Doctrine\DBAL\Sharding\PoolingShardConnection;
use function sprintf;


/**
 * Base class for Doctrine console commands to extend from.
 */
abstract class DoctrineCommand extends Command
{
    /**
     * @var ManagerRegistry
     */
    private ManagerRegistry $doctrine;


    public function __construct( ManagerRegistry $doctrine )
    {
        parent::__construct();

        $this->doctrine = $doctrine;
    }


    /**
     * Get a doctrine entity manager by symfony name.
     *
     * @param string   $name
     * @param int|null $shardId
     *
     * @return \Doctrine\ORM\EntityManagerInterface|\Doctrine\Persistence\ObjectManager
     */
    protected function getEntityManager( string $name, int $shardId = null ): ObjectManager|EntityManagerInterface
    {
        $manager = $this->getDoctrine()->getManager( $name );

        if ( $shardId ) {
            if ( !$manager instanceof EntityManagerInterface )
                throw new LogicException(
                    sprintf(
                        'Sharding is supported only in EntityManager of instance "%s".',
                        EntityManagerInterface::class
                    )
                );

            $connection = $manager->getConnection();
            if ( !$connection instanceof PoolingShardConnection )
                throw new LogicException(
                    sprintf( "Connection of EntityManager '%s' must implement shards configuration.", $name )
                );

            $connection->connect( $shardId );
        }

        return $manager;
    }

    /**
     * @return ManagerRegistry
     */
    protected function getDoctrine(): ManagerRegistry
    {
        return $this->doctrine;
    }

    /**
     * Get a doctrine dbal connection by symfony name.
     *
     * @param string $name
     *
     * @return object
     */
    protected function getDoctrineConnection( string $name ): object
    {
        return $this->getDoctrine()->getConnection( $name );
    }

}
