<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Helpers;

final class StringCase
{
    /** @see string_to_snake() for full behaviour description and examples. */
    public static function snake(string $input): string
    {
        return implode('_', self::words($input));
    }

    /** @see string_to_screaming_snake() for full behaviour description and examples. */
    public static function screamingSnake(string $input): string
    {
        return mb_strtoupper(self::snake($input));
    }

    /** @see string_to_kebab() for full behaviour description and examples. */
    public static function kebab(string $input): string
    {
        return implode('-', self::words($input));
    }

    /** @see string_to_camel() for full behaviour description and examples. */
    public static function camel(string $input): string
    {
        $words = self::words($input);

        if ([] === $words) {
            return '';
        }

        $first = array_shift($words);

        return $first . implode(
            '',
            array_map(
                static fn (string $word): string => mb_convert_case(
                    $word,
                    MB_CASE_TITLE,
                ),
                $words,
            ),
        );
    }

    /** @see string_to_pascal() for full behaviour description and examples. */
    public static function pascal(string $input): string
    {
        return implode(
            '',
            array_map(
                static fn (string $word): string => mb_convert_case(
                    $word,
                    MB_CASE_TITLE,
                ),
                self::words($input),
            ),
        );
    }

    private static function words(string $input): array
    {
        $input = trim($input);

        if ('' === $input) {
            return [];
        }

        // split camelCase boundaries:
        // helloWorld => hello World
        $input = preg_replace(
            '/(?<=\p{Ll})(\p{Lu})/u',
            ' $1',
            $input,
        );

        // Split acronym boundaries:
        // XMLHttp => XML Http
        $input = preg_replace(
            '/(\p{Lu})(\p{Lu}\p{Ll})/u',
            '$1 $2',
            $input,
        );

        // Split digit→letter boundaries:
        // user1Profile => user1 Profile
        $input = preg_replace(
            '/(\d)(\p{Lu})/u',
            '$1 $2',
            $input,
        );

        // Normalize separators into spaces
        $input = preg_replace(
            '/[\s\-_.\/\\\\]+/u',
            ' ',
            $input,
        );

        $parts = preg_split(
            '/\s+/u',
            $input,
            -1,
            PREG_SPLIT_NO_EMPTY,
        );

        if (false === $parts) {
            return [];
        }

        return array_map(
            static fn (string $part): string => mb_strtolower($part),
            $parts,
        );
    }
}
