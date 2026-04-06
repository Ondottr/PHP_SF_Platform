<?php
declare(strict_types=1);

namespace PHP_SF\System;

use JetBrains\PhpStorm\Deprecated;
use PHP_SF\System\Classes\Helpers\Locale;
use PHP_SF\System\Core\TemplatesCache;
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
        require_once __DIR__.'/../functions/functions.php';

        if (DEV_MODE === true) {
            if (function_exists('apcu_clear_cache')) {
                apcu_clear_cache();
            }

            if (PHP_SAPI !== 'cli') {
                Debug::enable();
            }
        }

        $this->setDefaultLocale();

        $this->addControllers(__DIR__.'/../app/Http/Controller');

        $this->addTemplatesDirectory('vendor/nations-original/php-simple-framework/templates', 'PHP_SF\Templates');

        register_shutdown_function(function () {
            rp()->execute();
        });
    }

    public function addControllers(string $path): self
    {
        if (file_exists($path) === false) {
            throw new DirectoryNotFoundException("Controllers directory '$path' not found.");
        }

        Router::addControllersDirectory($path);

        return $this;
    }

    /** @deprecated No-op. Translation paths are now configured in config/packages/translation.yaml. */
    #[Deprecated(
        reason: 'No-op. Translation paths are now configured in config/packages/translation.yaml.',
        replacement: 'No direct replacement, translation paths should be configured in config/packages/translation.yaml.'
    )]
    public function addTranslationFiles(string $path): self
    {
        trigger_deprecation(
            'php-simple-framework',
            '1.1.9',
            'The method %s is deprecated since version 1.1.9 and will be removed in the next major release. '.
            'Translation paths are now configured in config/packages/translation.yaml.',
            __METHOD__
        );

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
                'Application user class name not specified, you need to specify it here: '.
                '`Kernel->setApplicationUserClassName(User::class)`'
            );
        }

        return self::$applicationUserClassName;
    }

    public function setApplicationUserClassName(string $className): self
    {
        if (class_exists($className) === false) {
            throw new InvalidConfigurationException(sprintf("User Class '%s' does not exist", $className));
        }

        self::$applicationUserClassName = $className;

        return $this;
    }

    public function setHeaderTemplateClassName(string $headerTemplateClassName): self
    {
        if (class_exists($headerTemplateClassName) === false) {
            throw new InvalidConfigurationException(
                "Header template class '$headerTemplateClassName' does not exist"
            );
        }

        self::$headerTemplateClassName = $headerTemplateClassName;

        return $this;
    }

    public function setFooterTemplateClassName(string $footerTemplateClassName): self
    {
        if (class_exists($footerTemplateClassName) === false) {
            throw new InvalidConfigurationException(
                "Footer template class '$footerTemplateClassName' does not exist"
            );
        }

        self::$footerTemplateClassName = $footerTemplateClassName;

        return $this;
    }


    private function setDefaultLocale(): void
    {
        if (s()->has('locale')) {
            define('DEFAULT_LOCALE', s()->get('locale'));

            return;
        }

        /** @noinspection GlobalVariableUsageInspection */
        if (array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER) === false) {
            define('DEFAULT_LOCALE', Locale::getLocaleKey(Locale::en));

            return;
        }

        $rc = new ReflectionClass(Locale::class);
        /** @noinspection GlobalVariableUsageInspection */
        $acceptLanguages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);

        foreach ($acceptLanguages as $langCode) {
            if ($rc->hasConstant($langCode) && in_array($langCode, LANGUAGES_LIST, true)) {
                define('DEFAULT_LOCALE', Locale::getLocaleKey($rc->getConstant($langCode)));
                s()->set('locale', DEFAULT_LOCALE);

                return;
            }
        }

        define('DEFAULT_LOCALE', LANGUAGES_LIST[0]);
    }

}
