<?php declare(strict_types=1);

namespace PHP_SF\System\Core;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinColumns;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class TranslatorV2
 *
 * Manages ICU-style translations loaded from YAML files.
 * Supports named {param} interpolation and lazy @:key references in parameter values.
 * Works standalone — no Symfony container required.
 *
 * @package PHP_SF\System\Core
 */
final class TranslatorV2 implements TranslatorInterface
{

    private const int    MAX_REF_DEPTH   = 5;
    private const string CACHE_KEY_PREFIX = 'translator_v2:';


    private static ?self  $instance = null;
    /**
     * Ordered list of registered directories; last entry is the DEV_MODE write target.
     */
    private static array  $dirs     = [];


    /**
     * Flat catalogs per locale: ['en' => ['some.key' => 'Some value']].
     */
    private array $catalogs       = [];
    private bool  $catalogsLoaded = false;


    private function __construct() {}

    private function __clone() {}


    public static function getInstance(): self
    {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register a directory containing {locale}.yaml translation files.
     * Directories registered later override earlier ones on key collision.
     * The last registered directory receives missing-key writes in DEV_MODE.
     */
    public static function addTranslationDir( string $dir ): void
    {
        if ( !is_dir( $dir ) ) {
            throw new DirectoryNotFoundException(
                sprintf( 'Translation directory "%s" does not exist.', $dir )
            );
        }

        self::$dirs[] = $dir;

        if ( self::$instance !== null ) {
            self::$instance->catalogsLoaded = false;
        }

        if ( defined( 'DEV_MODE' ) && DEV_MODE === true ) {
            self::getInstance()->catalogsLoaded = false;
            self::getInstance()->loadCatalogs();
        }
    }


    /**
     * Translate a key with optional named parameters.
     *
     * Parameters use {name} syntax in YAML values.
     * Parameter values prefixed with @: are resolved as translation key references (lazy).
     *
     * Example:
     *   _t('validation.too_long', ['field' => '@:entities.post.title'])
     *   YAML: validation.too_long: "{field} is too long"
     *   YAML: entities.post.title: "Title"
     *   Result: "Title is too long"
     *
     * @param string      $id         Translation key (dot-notation)
     * @param array       $parameters Named parameters; values may be @:key references
     * @param string|null $domain     Accepted for interface compatibility, ignored
     * @param string|null $locale     Defaults to current locale
     */
    public function trans( string $id, array $parameters = [], ?string $domain = null, ?string $locale = null ): string
    {
        $locale ??= Lang::getCurrentLocale();

        $this->loadCatalogs();

        $raw = $this->catalogs[$locale][$id] ?? null;

        if ( $raw === null && $locale !== DEFAULT_LOCALE ) {
            $raw = $this->catalogs[DEFAULT_LOCALE][$id] ?? null;
        }

        if ( $raw === null ) {
            return $this->handleMissingKey( $id, $locale );
        }

        return $this->resolveValue( $raw, $parameters, $locale, 0 );
    }

    public function getLocale(): string
    {
        return Lang::getCurrentLocale();
    }

    public function loadCatalogs(): void
    {
        if ( $this->catalogsLoaded ) {
            return;
        }

        $localesToLoad = LANGUAGES_LIST;

        if ( !in_array( DEFAULT_LOCALE, $localesToLoad, true ) ) {
            $localesToLoad[] = DEFAULT_LOCALE;
        }

        foreach ( $localesToLoad as $locale ) {
            $this->catalogs[$locale] = $this->loadLocale( $locale );
        }

        $this->catalogsLoaded = true;

        if ( DEV_MODE === true ) {
            $this->saveEntityFieldKeys();
            $this->synchronizeLocaleFiles();
        }
    }


    private function loadLocale( string $locale ): array
    {
        if ( DEV_MODE === false ) {
            $cached = ca()->get( self::CACHE_KEY_PREFIX . $locale );

            if ( $cached !== null ) {
                return json_decode( $cached, true, 512, JSON_THROW_ON_ERROR );
            }
        }

        $merged = [];

        foreach ( self::$dirs as $dir ) {
            $file = $dir . '/' . $locale . '.yaml';

            if ( !file_exists( $file ) ) {
                continue;
            }

            $data = Yaml::parseFile( $file );

            if ( is_array( $data ) ) {
                $merged = array_merge( $merged, $this->flattenKeys( $data ) );
            }
        }

        if ( DEV_MODE === false && !empty( $merged ) ) {
            ca()->set( self::CACHE_KEY_PREFIX . $locale, json_encode( $merged, JSON_THROW_ON_ERROR ) );
        }

        return $merged;
    }

    private function flattenKeys( array $data, string $prefix = '' ): array
    {
        $result = [];

        foreach ( $data as $key => $value ) {
            $fullKey = $prefix !== '' ? $prefix . '.' . $key : (string) $key;

            if ( is_array( $value ) ) {
                $result = array_merge( $result, $this->flattenKeys( $value, $fullKey ) );
            } else {
                $result[$fullKey] = (string) $value;
            }
        }

        return $result;
    }

    private function resolveValue( string $raw, array $parameters, string $locale, int $depth ): string
    {
        if ( empty( $parameters ) ) {
            return $raw;
        }

        $resolved = [];

        foreach ( $parameters as $name => $value ) {
            if ( is_string( $value ) && str_starts_with( $value, '@:' ) ) {
                $value = $this->resolveAtRef( substr( $value, 2 ), $locale, $depth );
            }

            $resolved[$name] = $value;
        }

        // Try ICU MessageFormatter first — handles plural, select, and simple {name} substitution.
        // Falls back to strtr() if the intl extension is unavailable or the value is not valid ICU.
        if ( class_exists( \MessageFormatter::class ) ) {
            $formatted = \MessageFormatter::formatMessage( $locale, $raw, $resolved );

            if ( $formatted !== false ) {
                return $formatted;
            }
        }

        $replacements = [];
        foreach ( $resolved as $name => $value ) {
            $replacements['{' . $name . '}'] = (string) $value;
        }

        return strtr( $raw, $replacements );
    }

    private function resolveAtRef( string $key, string $locale, int $depth ): string
    {
        if ( $depth >= self::MAX_REF_DEPTH ) {
            return $key;
        }

        $raw = $this->catalogs[$locale][$key]
            ?? ( $locale !== DEFAULT_LOCALE ? ( $this->catalogs[DEFAULT_LOCALE][$key] ?? null ) : null );

        if ( $raw === null ) {
            return $key;
        }

        return $this->resolveValue( $raw, [], $locale, $depth + 1 );
    }

    private function handleMissingKey( string $id, string $locale ): string
    {
        if ( DEV_MODE === true ) {
            $localesToWrite = LANGUAGES_LIST;

            if ( !in_array( DEFAULT_LOCALE, $localesToWrite, true ) ) {
                $localesToWrite[] = DEFAULT_LOCALE;
            }

            foreach ( $localesToWrite as $l ) {
                $this->catalogs[$l][$id] = $id . '_not_translated';
                $this->writeKeyToLastDir( $id, $l );
            }
        }

        return $id . '_not_translated';
    }

    /**
     * Scans App/Entity/ for Doctrine-mapped properties and registers translation keys
     * in the format "<entity_snake_case>.fields.<field_snake_case>".
     * Only properties with #[Column], #[JoinColumn], or #[JoinColumns] are included.
     * Skips keys that already exist in any locale's catalog.
     */
    private function saveEntityFieldKeys(): void
    {
        if ( empty( self::$dirs ) ) {
            return;
        }

        $entityDir = dirname( self::$dirs[array_key_last( self::$dirs )] ) . '/App/Entity';

        if ( !is_dir( $entityDir ) ) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $entityDir, \RecursiveDirectoryIterator::FOLLOW_SYMLINKS )
        );

        $newEntityKeys = [];

        foreach ( $iterator as $file ) {
            if ( !$file->isFile() || $file->getExtension() !== 'php' ) {
                continue;
            }

            $contents = file_get_contents( $file->getPathname() );

            if ( !preg_match( '/^namespace\s+([^;]+);/m', $contents, $nsMatch ) ) {
                continue;
            }

            $className = $nsMatch[1] . '\\' . $file->getBasename( '.php' );

            if ( !class_exists( $className ) ) {
                continue;
            }

            $rc          = new ReflectionClass( $className );
            $entityKey   = camel_to_snake( $rc->getShortName() );
            $properties  = $rc->getProperties( ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC );

            foreach ( $properties as $property ) {
                $attrs = $property->getAttributes();

                $hasMappedAttr = false;
                foreach ( $attrs as $attr ) {
                    $name = $attr->getName();
                    if ( $name === Column::class || $name === JoinColumn::class || $name === JoinColumns::class ) {
                        $hasMappedAttr = true;
                        break;
                    }
                }

                if ( !$hasMappedAttr ) {
                    continue;
                }

                $translationKey = $entityKey . '.fields.' . camel_to_snake( $property->getName() );

                foreach ( LANGUAGES_LIST as $locale ) {
                    if ( !array_key_exists( $translationKey, $this->catalogs[$locale] ) ) {
                        $this->catalogs[$locale][$translationKey] = $translationKey . '_not_translated';
                        $newEntityKeys[]                          = $translationKey;
                    }
                }
            }
        }

        if ( empty( $newEntityKeys ) ) {
            return;
        }

        $newEntityKeys = array_unique( $newEntityKeys );

        // Persist only the newly discovered entity keys to the last registered dir
        foreach ( LANGUAGES_LIST as $locale ) {
            $lastDir  = self::$dirs[array_key_last( self::$dirs )];
            $filePath = $lastDir . '/' . $locale . '.yaml';

            $existing = [];
            if ( file_exists( $filePath ) ) {
                $parsed = Yaml::parseFile( $filePath );
                if ( is_array( $parsed ) ) {
                    $existing = $this->flattenKeys( $parsed );
                }
            }

            $added = false;
            foreach ( $newEntityKeys as $key ) {
                if ( !array_key_exists( $key, $existing ) ) {
                    $existing[$key] = $key . '_not_translated';
                    $added          = true;
                }
            }

            if ( $added ) {
                ksort( $existing );
                file_put_contents( $filePath, Yaml::dump( $existing, 4, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK ) );
            }
        }
    }

    /**
     * Ensures all locale files within each registered directory contain the same set of keys.
     * Keys present in one locale but missing from another are written with a "_not_translated" value.
     */
    private function synchronizeLocaleFiles(): void
    {
        foreach ( self::$dirs as $dir ) {
            $dirCatalogs = [];

            foreach ( LANGUAGES_LIST as $locale ) {
                $file = $dir . '/' . $locale . '.yaml';

                if ( file_exists( $file ) ) {
                    $parsed              = Yaml::parseFile( $file );
                    $dirCatalogs[$locale] = is_array( $parsed ) ? $this->flattenKeys( $parsed ) : [];
                } else {
                    $dirCatalogs[$locale] = [];
                }
            }

            // Collect every key present in at least one locale in this dir
            $allKeys = array_unique( array_merge( ...array_values( array_map( 'array_keys', $dirCatalogs ) ) ) );

            if ( empty( $allKeys ) ) {
                continue;
            }

            foreach ( LANGUAGES_LIST as $locale ) {
                $needsWrite = false;

                foreach ( $allKeys as $key ) {
                    if ( !array_key_exists( $key, $dirCatalogs[$locale] ) ) {
                        $value                       = $key . '_not_translated';
                        $dirCatalogs[$locale][$key]  = $value;
                        $this->catalogs[$locale][$key] = $value;
                        $needsWrite                  = true;
                    }
                }

                if ( $needsWrite ) {
                    $data = $dirCatalogs[$locale];
                    ksort( $data );
                    file_put_contents( $dir . '/' . $locale . '.yaml', Yaml::dump( $data, 4, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK ) );
                }
            }
        }
    }

    private function writeKeyToLastDir( string $id, string $locale ): void
    {
        if ( empty( self::$dirs ) ) {
            return;
        }

        $lastDir  = self::$dirs[array_key_last( self::$dirs )];
        $filePath = $lastDir . '/' . $locale . '.yaml';

        $existing = [];

        if ( file_exists( $filePath ) ) {
            $parsed = Yaml::parseFile( $filePath );

            if ( is_array( $parsed ) ) {
                $existing = $this->flattenKeys( $parsed );
            }
        }

        if ( !array_key_exists( $id, $existing ) ) {
            $existing[$id] = $id . '_not_translated';
        }

        ksort( $existing );

        file_put_contents( $filePath, Yaml::dump( $existing, 4, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK ) );
    }

}
