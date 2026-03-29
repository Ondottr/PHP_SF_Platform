<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

abstract class AbstractEntityMaker extends AbstractMaker
{

    protected string $entityNamespace;
    protected string $repositoryNamespace;
    protected string $entityDir;
    protected string $repositoryDir;
    protected string $schema;


    public function __construct(
        private readonly CommandLoaderInterface $commandLoader,
    ) {}

    abstract public static function getCommandName(): string;

    public static function getCommandDescription(): string
    {
        return 'Creates a new entity & repository for a specific DB schema';
    }

    public function configureCommand( Command $command, InputConfiguration $inputConfig )
    {
        $command
            ->setName( static::getCommandName() )
            ->setDescription( static::getCommandDescription() )
            ->addArgument( 'name', InputArgument::REQUIRED, 'The class name of the entity (e.g. User)' );
    }

    public function generate( InputInterface $input, ConsoleStyle $io, Generator $generator )
    {
        $className = Str::asClassName( $input->getArgument( 'name' ) );
        $entityFqcn = $this->entityNamespace . '\\' . $className;
        $entityPath = $this->entityDir . '/' . $className . '.php';

        if ( file_exists( $entityPath ) ) {
            $io->warning( sprintf(
                'Entity "%s\\%s" already exists. To add fields, edit the file directly or run make:entity with the full FQCN manually.',
                $this->entityNamespace,
                $className,
            ) );

            return Command::INVALID;
        }

        // otherwise → generate from our skeletons
        $generator->generateFile(
            $entityPath,
            __DIR__ . '/../../../Resources/skeleton/entity.tpl.php',
            [
                'namespace'           => $this->entityNamespace,
                'class_name'          => $className,
                'repositoryNamespace' => $this->repositoryNamespace,
                'table_name'          => Str::asSnakeCase( $className ),
                'schema'              => $this->schema,
            ]
        );

        $repositoryClass = $className . 'Repository';
        $repositoryPath  = $this->repositoryDir . '/' . $repositoryClass . '.php';

        if ( !file_exists( $repositoryPath ) ) {
            $generator->generateFile(
                $repositoryPath,
                __DIR__ . '/../../../Resources/skeleton/repository.tpl.php',
                [
                    'namespace'    => $this->repositoryNamespace,
                    'class_name'   => $className,
                    'entity_class' => $className,
                    'entityFqcn'   => $entityFqcn,
                ]
            );
        }

        $generator->writeChanges();

        $io->success( sprintf(
            'Entity "%s" and repository "%sRepository" created (schema: %s)',
            $entityFqcn,
            $className,
            $this->schema
        ) );

        return Command::SUCCESS;
    }


    public function configureDependencies( DependencyBuilder $dependencies )
    {
        // we don’t need DoctrineBundle internal stuff, just make sure Doctrine exists
    }

}
