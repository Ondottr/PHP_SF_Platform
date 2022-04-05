<?php declare( strict_types=1 );

namespace PHP_SF\System\Core;

use function in_array;

final class Lang
{

    public static function getCurrentLocale(): string
    {
        return s()->get('locale', DEFAULT_LOCALE);
    }

    public static function setCurrentLocale(string $locale): void
    {
        if (in_array($locale, LANGUAGES_LIST, true)) {
            s()->set('locale', $locale);
        }
    }
}
