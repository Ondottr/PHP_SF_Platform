<?php declare( strict_types=1 );

namespace PHP_SF\System;

use PHP_SF\System\Classes\Helpers\Locale;
use PHP_SF\System\Core\TemplatesCache;
use PHP_SF\System\Core\Translator;
use PHP_SF\System\Database\DoctrineEntityManager;
use PHP_SF\Templates\Layout\footer;
use PHP_SF\Templates\Layout\header;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

use function array_key_exists;
use function define;
use function in_array;

final class Kernel
{

    private static string $applicationUserClassName = '';
    private static string $headerTemplateClassName  = header::class;
    private static string $footerTemplateClassName  = footer::class;

    public function __construct()
    {
        require_once __DIR__ . '/../functions/functions.php';

        if ( DEV_MODE === true ) {
            if ( function_exists( 'apcu_clear_cache' ) )
                apcu_clear_cache();

            Debug::enable();
        }

        $this->setDefaultLocale();

        $this->addControllers( __DIR__ . '/../app/Http/Controller' );

        $this->addEntities( ENTITY_DIRECTORY );

        $this->addTranslationFiles( __DIR__ . '/../lang' );

        $this->addTemplatesDirectory( 'Platform/templates', 'PHP_SF\Templates' );

        register_shutdown_function( static function () {
            rp()->execute();
        } );
    }

    public function addControllers(string $path): self
    {
        if ( file_exists($path) === false )
            throw new DirectoryNotFoundException("Controllers directory '$path' not found.");

        Router::addControllersDirectory($path);

        return $this;
    }

    public function addEntities(string $path): self
    {
        if (file_exists($path) === false ) {
            throw new DirectoryNotFoundException( "Entities directory '$path' could not be found." );
        }

        DoctrineEntityManager::addEntityDirectory($path);

        return $this;
    }

    public function addTranslationFiles(string $path): self
    {
        if (file_exists($path) === false)
            throw new DirectoryNotFoundException( "Translation directory '$path' could not be found." );

        Translator::addTranslationDirectory($path);

        return $this;
    }

    public function addTemplatesDirectory(string $templatesDirectoryName, string $namespaceName): self
    {
        TemplatesCache::addTemplatesNamespace($namespaceName);
        TemplatesCache::addTemplatesDirectory($templatesDirectoryName);

        return $this;
    }

    public static function getHeaderTemplateClassName(): string
    {
        return self::$headerTemplateClassName;
    }

    public static function getFooterTemplateClassName(): string
    {
        return self::$footerTemplateClassName;
    }

    public static function getApplicationUserClassName(): string
    {
        if (empty(self::$applicationUserClassName)) {
            throw new InvalidConfigurationException(
                'Application user class name not specified, you need to specify it here: ' .
                '`Kernel->setApplicationUserClassName(User::class)`'
            );
        }

        return self::$applicationUserClassName;
    }

    public function setApplicationUserClassName(string $className): self
    {
        if (class_exists($className) === false)
            throw new InvalidConfigurationException(sprintf( "User Class '%s' does not exist", $className));

        self::$applicationUserClassName = $className;

        return $this;
    }

    public function setHeaderTemplateClassName( string $headerTemplateClassName ): self
    {
        if ( class_exists( $headerTemplateClassName ) === false ) {
            throw new InvalidConfigurationException(
                "Header template class '$headerTemplateClassName' does not exist"
            );
        }

        self::$headerTemplateClassName = $headerTemplateClassName;

        return $this;
    }

    public function setFooterTemplateClassName( string $footerTemplateClassName ): self
    {
        if ( class_exists( $footerTemplateClassName ) === false ) {
            throw new InvalidConfigurationException(
                "Footer template class '$footerTemplateClassName' does not exist"
            );
        }

        self::$footerTemplateClassName = $footerTemplateClassName;

        return $this;
    }


    private function setDefaultLocale(): void
    {
        /** @noinspection GlobalVariableUsageInspection */
        if ( array_key_exists( 'HTTP_ACCEPT_LANGUAGE', $_SERVER ) === false || s()->has( 'locale' ) ) {
            define( 'DEFAULT_LOCALE', Locale::getLocaleKey( Locale::en ) );

            return;
        }

        $rc = new ReflectionClass( Locale::class );
        /** @noinspection GlobalVariableUsageInspection */
        $acceptLanguages = explode( ',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] );

        foreach ( $acceptLanguages as $langCode )
            if ( $rc->hasConstant( $langCode ) && in_array( $langCode, LANGUAGES_LIST, true ) ) {
                define( 'DEFAULT_LOCALE', Locale::getLocaleKey( $rc->getConstant( $langCode ) ) );
                s()->set( 'locale', DEFAULT_LOCALE );
                return;
            }

        define( 'DEFAULT_LOCALE', LANGUAGES_LIST[0] );
    }

}
