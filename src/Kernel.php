<?php declare( strict_types=1 );

namespace PHP_SF\System;

use PHP_SF\System\Core\TemplatesCache;
use PHP_SF\System\Core\Translator;
use PHP_SF\System\Database\DoctrineEntityManager;
use PHP_SF\Templates\Layout\footer;
use PHP_SF\Templates\Layout\header;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;


final class Kernel
{

    private static string $applicationUserClassName = '';
    private static string $headerTemplateClassName  = header::class;
    private static string $footerTemplateClassName  = footer::class;

    public function __construct()
    {
        require_once __DIR__ . '/../functions/functions.php';

        if (DEV_MODE === true) {
            apcu_clear_cache();
            Debug::enable();
        }

        $this->addControllers(__DIR__ . '/../app/Http/Controller');

        $this->addEntities(ENTITY_DIRECTORY);

        $this->addTranslationFiles(__DIR__ . '/../lang');

        $this->addTemplatesDirectory('Platform/templates', 'PHP_SF\Templates');

        register_shutdown_function(static function () {

            rp()->execute();
        });
    }

    public function addControllers(string $path): self
    {
        if (!file_exists($path)) {
            throw new DirectoryNotFoundException(sprintf('Controllers directory "%s" could not be found.', $path));
        }

        Router::addControllersDirectory($path);

        return $this;
    }

    public function addEntities(string $path): self
    {
        if (!file_exists($path)) {
            throw new DirectoryNotFoundException(sprintf('Entities directory "%s" could not be found.', $path));
        }

        DoctrineEntityManager::addEntityDirectory($path);

        return $this;
    }

    public function addTranslationFiles(string $path): self
    {
        if (!file_exists($path)) {
            throw new DirectoryNotFoundException(sprintf('Translation directory "%s" could not be found.', $path));
        }

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
        if (!class_exists($className))
            throw new InvalidConfigurationException(sprintf('User Class "%s" does not exist', $className));

        self::$applicationUserClassName = $className;

        return $this;
    }

    public function setHeaderTemplateClassName(string $headerTemplateClassName): self
    {
        if (!class_exists($headerTemplateClassName)) {
            throw new InvalidConfigurationException(sprintf(
                'Header template class "%s" does not exist',
                $headerTemplateClassName
            ));
        }

        self::$headerTemplateClassName = $headerTemplateClassName;

        return $this;
    }

    public function setFooterTemplateClassName(string $footerTemplateClassName): self
    {
        if (!class_exists($footerTemplateClassName)) {
            throw new InvalidConfigurationException(sprintf(
                'Footer template class "%s" does not exist',
                $footerTemplateClassName
            ));
        }

        self::$footerTemplateClassName = $footerTemplateClassName;

        return $this;
    }


    public function init(): void
    {
        Router::init();
    }

}
