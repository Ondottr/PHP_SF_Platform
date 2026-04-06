<?php

declare(strict_types=1);

namespace PHP_SF\System\Core;

use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\Translation\Formatter\IntlFormatter;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator as SymfonyTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;


final class Translator
{

    private const DOMAIN = 'messages+intl-icu';

    private static ?TranslatorInterface $symfony = null;


    public static function setSymfonyTranslator(TranslatorInterface $translator): void
    {
        self::$symfony = $translator;
    }

    /**
     * Boots a lightweight standalone Symfony translator (no kernel, no DI container).
     * Loads all *.yaml files from the provided directories for every locale subdirectory
     * found in each path. Call this before Router::init() so PHP-SF routes have translations.
     *
     * The full DI-managed translator (injected by TranslatorBootSubscriber) will replace
     * this instance for Symfony-handled routes automatically.
     *
     * @param string ...$translationDirs Absolute paths to directories containing YAML translation files.
     */
    public static function bootStandalone(string ...$translationDirs): void
    {
        $translator = new SymfonyTranslator(
            Lang::getCurrentLocale(),
            new MessageFormatter(intlFormatter: new IntlFormatter()),
        );

        $translator->addLoader('yaml', new YamlFileLoader());

        foreach ($translationDirs as $dir) {
            if ( !is_dir($dir)) {
                continue;
            }

            foreach (glob($dir.'/*.yaml') ?: [] as $file) {
                // Filename pattern: messages+intl-icu.en.yaml  →  locale = en
                $basename = basename($file, '.yaml');
                $parts    = explode('.', $basename);

                if (count($parts) < 2) {
                    continue;
                }

                $locale = array_pop($parts);
                $domain = implode('.', $parts);

                $translator->addResource('yaml', $file, $locale, $domain);
            }
        }

        self::$symfony = $translator;
    }


    /** @deprecated No-op. Translation paths are now configured in config/packages/translation.yaml. */
    #[Deprecated(
        reason: 'No-op. Translation paths are now configured in config/packages/translation.yaml.',
        replacement: 'No direct replacement, translation paths should be configured in config/packages/translation.yaml.'
    )]
    public static function addTranslationDirectory(string $translationDirectory): void
    {
        trigger_deprecation(
            'php-simple-framework',
            '1.1.9',
            'The method %s is deprecated since version 1.1.9 and will be removed in the next major release. '.
            'Translation paths are now configured in config/packages/translation.yaml.',
            __METHOD__
        );
    }


    public function translate(string $key, array $parameters = []): string
    {
        if (self::$symfony === null) {
            return $key;
        }

        return self::$symfony->trans($key, $parameters, self::DOMAIN, Lang::getCurrentLocale());
    }

    public function translateTo(string $key, string $locale, array $parameters = []): string
    {
        if (self::$symfony === null) {
            return $key;
        }

        return self::$symfony->trans($key, $parameters, self::DOMAIN, $locale);
    }

    public static function getInstance(): self
    {
        return new self();
    }

}
