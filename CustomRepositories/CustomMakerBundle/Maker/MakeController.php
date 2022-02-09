<?php declare( strict_types=1 );

namespace Symfony\Bundle\MakerBundle\Maker;

use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Doctrine\Common\Annotations\Annotation;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;


final class MakeController extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:controller';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new controller class';
    }

    public function configureCommand( Command $command, InputConfiguration $inputConfig ): void
    {
        $command
            ->addArgument(
                'controller-class',
                InputArgument::OPTIONAL,
                sprintf(
                    'Choose a name for your controller class (e.g. <fg=yellow>%sController</>)',
                    Str::asClassName( Str::getRandomTerm() )
                )
            )
            ->addOption(
                'is-symfony-type',
                null,
                InputOption::VALUE_OPTIONAL,
                'Use this option to create symfony controller or PHP_SF controller without this option',
                false
            )
            ->setHelp(
                'The <info>%command.name%</info> command generates a new controller class.
<info>php %command.full_name% CoolStuffController</info>
If the argument is missing, the command will ask for the controller class name interactively.
You can also generate the controller alone, without template with this option:
<info>php %command.full_name% --no-template</info>
'
            );
    }

    public function generate( InputInterface $input, ConsoleStyle $io, Generator $generator ): void
    {
        $isSymfonyType  = $input->getOption( 'is-symfony-type' );
        $subdirectories = explode( '/', $input->getArgument( 'controller-class' ) );

        $controllerClassNameDetails = $generator->createClassNameDetails(
            array_pop( $subdirectories ),
            'Http\\Controller\\' . count( $subdirectories ) > 0 ? implode( '\\', $subdirectories ) : '',
            'Controller'
        );

        dd( $controllerClassNameDetails );

        if ( $isSymfonyType !== false ) {
            $controllerSkeleton = 'controller/SymfonyController.tpl.php';
            $templateSkeleton   = 'controller/twig_template.tpl.php';
            $templateName       = sprintf(
                'twig/%s/%s_page.html.twig',
                snakeToCamel( Str::asFilePath( $controllerClassNameDetails->getRelativeNameWithoutSuffix() ) ),
                camel_to_snake( Str::asFilePath( $controllerClassNameDetails->getRelativeNameWithoutSuffix() ) )
            );
        }
        else {
            $controllerSkeleton = 'controller/PHP_SFController.tpl.php';
            $templateSkeleton   = 'controller/view_template.tpl.php';
            $templateName       = sprintf(
                '%s/%s_page.php',
                ucfirst(
                    snakeToCamel( Str::asFilePath( $controllerClassNameDetails->getRelativeNameWithoutSuffix() ) )
                ),
                camel_to_snake( Str::asFilePath( $controllerClassNameDetails->getRelativeNameWithoutSuffix() ) )
            );
        }

        $controllerPath = $generator->generateController(
            $controllerClassNameDetails->getFullName(),
            $controllerSkeleton,
            [
                'route_path'    => Str::asRoutePath( $controllerClassNameDetails->getRelativeNameWithoutSuffix() ),
                'route_name'    => Str::asRouteName( $controllerClassNameDetails->getRelativeNameWithoutSuffix() ),
                'with_template' => ( $isSymfonyType === false || $this->isTwigInstalled() ),
                'template_name' => $templateName,
            ]
        );

        if ( $isSymfonyType === false || $this->isTwigInstalled() ) {
            $generator->generateTemplate(
                $templateName,
                $templateSkeleton,
                [
                    'controller_path' => $controllerPath,
                    'root_directory'  => $generator->getRootDirectory(),
                    'class_name'      => camel_to_snake(
                                             Str::asFilePath(
                                                 $controllerClassNameDetails->getRelativeNameWithoutSuffix()
                                             )
                                         ) . '_page',
                ]
            );
        }

        $generator->writeChanges();

        $this->writeSuccessMessage( $io );
        $io->text( 'Next: Open your new controller class and add some pages!' );
    }

    private function isTwigInstalled(): bool
    {
        return class_exists( TwigBundle::class );
    }

    public function configureDependencies( DependencyBuilder $dependencies ): void
    {
        $dependencies->addClassDependency(
            Annotation::class,
            'doctrine/annotations'
        );
    }

}
