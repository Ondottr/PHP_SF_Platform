<?php declare(strict_types=1);

namespace PHP_SF\System;

use PHP_SF\System\Classes\Helpers\Locale;
use PHP_SF\System\Core\PhpSfEventDispatcher;
use PHP_SF\System\Core\TemplatesCache;
use PHP_SF\System\Core\TranslatorV2;
use PHP_SF\Templates\Layout\footer;
use PHP_SF\Templates\Layout\header;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class Kernel implements HttpKernelInterface
{
    /** @var class-string */
    private static string $applicationUserClassName = '';
    private static string $headerTemplateClassName = header::class;
    private static string $footerTemplateClassName = footer::class;

    public function __construct()
    {
        if (DEV_MODE === true) {
            if (function_exists('apcu_clear_cache')) {
                apcu_clear_cache();
            }

            if (PHP_SAPI !== 'cli') {
                Debug::enable();
            }
        }

        $this->setDefaultLocale();

        $this->addControllers(__DIR__ . '/../app/Http/Controller');

        $this->addTranslationFiles(__DIR__ . '/../lang');

        $this->addTemplatesDirectory('vendor/nations-original/php-simple-framework/templates', 'PHP_SF\Templates');

        PhpSfEventDispatcher::addSubscriberDirectory(__DIR__ . '/../app/EventSubscriber');

        register_shutdown_function(function () {
            rp()->execute();
        });
    }

    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
    {
        throw new \LogicException('PHP_SF\System\Kernel does not support Symfony request handling.');
    }

    public function addEventSubscriberDirectory(string $dir): self
    {
        PhpSfEventDispatcher::addSubscriberDirectory($dir);

        return $this;
    }

    public function addControllers(string $path): self
    {
        if (false === file_exists($path)) {
            throw new DirectoryNotFoundException("Controllers directory '$path' not found.");
        }

        Router::addControllersDirectory($path);

        return $this;
    }

    public function addTranslationFiles(string $path): self
    {
        if (false === file_exists($path)) {
            throw new DirectoryNotFoundException("Translation directory '$path' could not be found.");
        }

        TranslatorV2::addTranslationDir($path);

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

    /** @return class-string */
    public static function getApplicationUserClassName(): string
    {
        if (empty(self::$applicationUserClassName)) {
            throw new InvalidConfigurationException(
                'Application user class name not specified, you need to specify it here: ' .
                '`Kernel->setApplicationUserClassName(User::class)`',
            );
        }

        return self::$applicationUserClassName;
    }

    /** @param class-string $className */
    public function setApplicationUserClassName(string $className): self
    {
        if (false === class_exists($className)) {
            throw new InvalidConfigurationException(sprintf("User Class '%s' does not exist", $className));
        }

        self::$applicationUserClassName = $className;

        return $this;
    }

    public static function setHeaderTemplateClassName( string $headerTemplateClassName ): void
    {
        if (false === class_exists($headerTemplateClassName)) {
            throw new InvalidConfigurationException(
                "Header template class '$headerTemplateClassName' does not exist",
            );
        }

        self::$headerTemplateClassName = $headerTemplateClassName;
    }

    public static function setFooterTemplateClassName( string $footerTemplateClassName ): void
    {
        if (false === class_exists($footerTemplateClassName)) {
            throw new InvalidConfigurationException(
                "Footer template class '$footerTemplateClassName' does not exist",
            );
        }

        self::$footerTemplateClassName = $footerTemplateClassName;
    }

    private function setDefaultLocale(): void
    {
        if (s()->has('locale')) {
            \define('DEFAULT_LOCALE', s()->get('locale'));

            return;
        }

        /** @noinspection GlobalVariableUsageInspection */
        if (false === \array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER)) {
            \define('DEFAULT_LOCALE', Locale::getLocaleKey(Locale::en));

            return;
        }

        $rc = new \ReflectionClass(Locale::class);
        /** @noinspection GlobalVariableUsageInspection */
        $acceptLanguages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);

        foreach ($acceptLanguages as $langCode) {
            if ($rc->hasConstant($langCode) && \in_array($langCode, LANGUAGES_LIST, true)) {
                \define('DEFAULT_LOCALE', Locale::getLocaleKey($rc->getConstant($langCode)));
                s()->set('locale', DEFAULT_LOCALE);

                return;
            }
        }

        \define('DEFAULT_LOCALE', LANGUAGES_LIST[0]);
    }
}
