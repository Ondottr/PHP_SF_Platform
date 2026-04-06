<?php
declare(strict_types=1);

namespace PHP_SF\System\Core;

use JetBrains\PhpStorm\Deprecated;
use Symfony\Contracts\Translation\TranslatorInterface;


final class Translator
{

    private const DOMAIN = 'messages+intl-icu';

    private static ?TranslatorInterface $symfony = null;


    public static function setSymfonyTranslator(TranslatorInterface $translator): void
    {
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
