<?php

namespace Symfony\Bundle\MakerBundle\Doctrine;

use RuntimeException;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\NamingStrategy;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Symfony\Bundle\MakerBundle\Util\PhpCompatUtil;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Mapping\AbstractClassMetadataFactory;
use Doctrine\ORM\Mapping\MappingException as ORMMappingException;
use Doctrine\Persistence\Mapping\MappingException as PersistenceMappingException;
use function is_object;

final class DoctrineHelper
{
    private string           $entityNamespace;
    private PhpCompatUtil    $phpCompatUtil;
    private ?ManagerRegistry $registry;

    private ?array $mappingDriversByPrefix;

    private bool $attributeMappingSupport;

    public function __construct(
        string          $entityNamespace,
        PhpCompatUtil   $phpCompatUtil,
        ManagerRegistry $registry = null,
        bool            $attributeMappingSupport = false,
        array           $annotatedPrefixes = null
    ) {
        $this->entityNamespace         = trim( $entityNamespace, '\\' );
        $this->phpCompatUtil           = $phpCompatUtil;
        $this->registry                = $registry;
        $this->attributeMappingSupport = $attributeMappingSupport;
        $this->mappingDriversByPrefix  = $annotatedPrefixes;
    }

    public function getRegistry(): ManagerRegistry
    {
        // this should never happen: we will have checked for the
        // DoctrineBundle dependency before calling this
        if ( $this->registry === null )
            throw new RuntimeException( 'Somehow the doctrine service is missing. Is DoctrineBundle installed?' );


        return $this->registry;
    }

    private function isDoctrineInstalled(): bool
    {
        return $this->registry !== null;
    }

    public function getEntityNamespace(): string
    {
        return $this->entityNamespace;
    }

    public function doesClassUseDriver( string $className, string $driverClass ): bool
    {
        /**
         * @var EntityManagerInterface $em
         */
        $em = $this->getRegistry()->getManagerForClass( $className );

        if ( $em === null )
            throw new InvalidArgumentException(
                sprintf( 'Cannot find the entity manager for class "%s"', $className )
            );

        if ( $this->mappingDriversByPrefix === null ) {
            // doctrine-bundle <= 2.2
            $metadataDriver = $em->getConfiguration()->getMetadataDriverImpl();

            if ( !$this->isInstanceOf( $metadataDriver, MappingDriverChain::class ) )
                return $this->isInstanceOf( $metadataDriver, $driverClass );

            foreach ( $metadataDriver->getDrivers() as $namespace => $driver )
                if ( str_starts_with( $className, $namespace ) )
                    return $this->isInstanceOf( $driver, $driverClass );


            return $this->isInstanceOf( $metadataDriver->getDefaultDriver(), $driverClass );
        }

        $managerName = array_search( $em, $this->getRegistry()->getManagers(), true );

        foreach ( $this->mappingDriversByPrefix[ $managerName ] as [$prefix, $prefixDriver] )
            if ( str_starts_with( $className, $prefix ) )
                return $this->isInstanceOf( $prefixDriver, $driverClass );


        return false;
    }

    public function isClassAnnotated( string $className ): bool
    {
        return $this->doesClassUseDriver( $className, AnnotationDriver::class );
    }

    public function doesClassUsesAttributes( string $className ): bool
    {
        return $this->doesClassUseDriver( $className, AttributeDriver::class );
    }

    public function isDoctrineSupportingAttributes(): bool
    {
        return $this->isDoctrineInstalled() &&
               $this->attributeMappingSupport &&
               $this->phpCompatUtil->canUseAttributes();
    }

    public function getEntitiesForAutocomplete(): array
    {
        $entities = [];

        if ( $this->isDoctrineInstalled() ) {
            $allMetadata = $this->getMetadata();

            foreach ( array_keys( $allMetadata ) as $classname ) {
                $entityClassDetails = new ClassNameDetails( $classname, $this->entityNamespace );
                $entities[]         = $entityClassDetails->getRelativeName();
            }
        }

        sort( $entities );

        return $entities;
    }

    /**
     * @return array|ClassMetadata
     */
    public function getMetadata( string $classOrNamespace = null, bool $disconnected = false )
    {

        $metadata = [];

        /** @var EntityManagerInterface $em */
        foreach ( $this->getRegistry()->getManagers() as $em ) {
            $cmf = $em->getMetadataFactory();

            if ( $disconnected ) {
                try {
                    $loaded = $cmf->getAllMetadata();
                } catch ( ORMMappingException|PersistenceMappingException $e ) {
                    $loaded = $this->isInstanceOf( $cmf, AbstractClassMetadataFactory::class )
                        ? $cmf->getLoadedMetadata() : [];
                }

                $cmf = new DisconnectedClassMetadataFactory();
                $cmf->setEntityManager( $em );

                foreach ( $loaded as $m ) {
                    $cmf->setMetadataFor( $m->getName(), $m );
                }

                if ( $this->mappingDriversByPrefix === null ) {
                    // Invalidating the cached AnnotationDriver::$classNames to find new Entity classes
                    $metadataDriver = $em->getConfiguration()->getMetadataDriverImpl();

                    if ( $this->isInstanceOf( $metadataDriver, MappingDriverChain::class ) ) {
                        foreach ( $metadataDriver->getDrivers() as $driver ) {

                            if ( $this->isInstanceOf( $driver, AnnotationDriver::class ) )
                                $classNames->setValue( $driver, null );

                            if ( $this->isInstanceOf( $driver, AttributeDriver::class ) )
                                $classNames->setValue( $driver, null );

                        }
                    }
                }
            }

            foreach ( $cmf->getAllMetadata() as $m ) {
                if ( $classOrNamespace === null )
                    $metadata[ $m->getName() ] = $m;

                else {
                    if ( $m->getName() === $classOrNamespace )
                        return $m;


                    if ( str_starts_with( $m->getName(), $classOrNamespace ) )
                        $metadata[ $m->getName() ] = $m;

                }
            }
        }

        return $metadata;
    }

    public function createDoctrineDetails( string $entityClassName ): ?EntityDetails
    {
        $metadata = $this->getMetadata( $entityClassName );

        if ( $this->isInstanceOf( $metadata, ClassMetadata::class ) )
            return new EntityDetails( $metadata );


        return null;
    }

    public function isClassAMappedEntity( string $className ): bool
    {
        if ( !$this->isDoctrineInstalled() )
            return false;


        return (bool)$this->getMetadata( $className );
    }

    private function isInstanceOf( $object, string $class ): bool
    {
        if ( !is_object( $object ) )
            return false;


        return $object instanceof $class;
    }

    public function getPotentialTableName( string $className ): string
    {
        $entityManager = $this->getRegistry()->getManager();

        if ( !$entityManager instanceof EntityManagerInterface )
            throw new RuntimeException( 'ObjectManager is not an EntityManagerInterface.' );


        /** @var NamingStrategy $namingStrategy */
        $namingStrategy = $entityManager->getConfiguration()->getNamingStrategy();

        return $namingStrategy->classToTableName( $className );
    }

    public function isKeyword( string $name ): bool
    {
        /** @var Connection $connection */
        $connection = $this->getRegistry()->getConnection();

        return $connection->getDatabasePlatform()->getReservedKeywordsList()->isKeyword( $name );
    }

    /**
     * this method tries to find the correct MappingDriver for the given namespace/class
     * To determine which MappingDriver belongs to the class we check the prefixes configured in Doctrine and use the
     * prefix that has the closest match to the given $namespace.
     *
     * this helper function is needed to create entities with the configuration of doctrine if they are not yet been
     * registered in the ManagerRegistry
     */
    private function getMappingDriverForNamespace( string $namespace ): ?MappingDriver
    {
        $lowestCharacterDiff = null;
        $foundDriver         = null;

        foreach ( $this->mappingDriversByPrefix ?? [] as $mappings ) {
            foreach ( $mappings as [$prefix, $driver] ) {
                $diff = substr_compare( $namespace, $prefix, 0 );

                if ( $diff >= 0 && ( $lowestCharacterDiff === null || $diff < $lowestCharacterDiff ) ) {
                    $lowestCharacterDiff = $diff;
                    $foundDriver         = $driver;
                }
            }
        }

        return $foundDriver;
    }
}
