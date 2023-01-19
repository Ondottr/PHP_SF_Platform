<?php declare( strict_types=1 );

namespace PHP_SF\System\Core;

use PHP_SF\System\Classes\Exception\UndefinedLocaleKeyException;
use PHP_SF\System\Classes\Exception\UndefinedLocaleNameException;
use PHP_SF\System\Classes\Helpers\Locale;
use RuntimeException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use function array_key_exists;

final class Translator
{

    private static array $allTranslationsKeys    = [];
    private static self  $translator;
    private static array $translationDirectories = [];
    private static bool  $saveEnabled            = true;


    private function __construct()
    {
        $this->loadTranslation();
    }


    private function loadTranslation(): void
    {
        if ( empty( self::$translationDirectories ) )
            throw new InvalidConfigurationException( 'No translation file directories specified!' );


        foreach ( LANGUAGES_LIST as $locale ) {
            foreach ( self::getTranslationDirectories() as $translationDirectory ) {
                $redisKey = sprintf( 'translated_strings:%s', $locale );

                if ( ( $translations = rc()->get( $redisKey ) ) === null ) {
                    if ( file_exists( ( $path = sprintf( '%s/%s.php', $translationDirectory, $locale ) ) ) === false )
                        // @formatter::off
                        file_put_contents( $path, <<<'EOF'
<?php /** @noinspection ALL @formatter::off */ return [
/**
* This file was automatically generated
* You can add new translated strings in format below or use {@see _t()} function.
* "string_for_translation" string => "Translation for string with arguments: %s, %s"
* All provided strings in {@see _t()} function will be automatically added to this and another locale translation files
*/
];
EOF ); // @formatter::on

                    elseif ( isset( $this->$locale ) && !empty( $this->$locale ) )
                        /** @noinspection SlowArrayOperationsInLoopInspection */
                        $this->$locale = array_merge( $this->$locale, ( require $path ) );

                    else
                        $this->$locale = require $path;


                    if ( DEV_MODE === false )
                        rp()->set( $redisKey, json_encode( $this->$locale ) );

                } else
                    $this->$locale = json_decode( $translations, true, 512, JSON_THROW_ON_ERROR );

            }
        }
    }

    private static function getTranslationDirectories(): array
    {
        return self::$translationDirectories;
    }

    public static function getInstance(): self
    {
        if ( !isset( self::$translator ) )
            return self::setInstance();


        return self::$translator;
    }

    private static function setInstance(): self
    {
        return self::$translator = new self();
    }

    public static function addTranslationDirectory( string $translationDirectory ): void
    {
        self::$translationDirectories[] = $translationDirectory;
    }

    public static function enableSave(): void
    {
        self::$saveEnabled = true;
    }

    public static function disableSave(): void
    {
        self::$saveEnabled = false;
    }

    public function translate( string $string, ...$values ): string
    {
        $locale = Lang::getCurrentLocale();

        if ( !array_key_exists( $string, $this->$locale ) && self::isSaveEnabled() )
            $this->addTranslateStringToLocaleFile( $string );


        return sprintf( ( $this->$locale )[ $string ] ?? $string, ...$values );
    }

    /**
     * Returns translation from provided object or array and for current or provided locale
     *
     * Select localeKey from {@see Locale}
     * using methods {@see Locale::getLocaleKey()} and {@see Locale::getLocaleName()}
     *
     * Array or object must be in format:
     *
     * [ "en" => "English translation", "bg" => "Bulgarian translation" ]
     *
     * { "en": "English translation", "bg": "Bulgarian translation" }
     *
     * @throws UndefinedLocaleKeyException|UndefinedLocaleNameException
     * @throws InvalidConfigurationException if provided locale is not supported ( {@see LANGUAGES_LIST} )
     * @throws RuntimeException if provided object or array is not in correct format or empty
     */
    public function translateFromArray( array|object $object, string|null $localeName = null, string|null $localeKey = null ): string|array|object
    {
        if ( is_object( $object ) )
            $object = (array)$object;

        if ( $localeName === null && $localeKey === null )
            // Using current Locale
            $translateTo = Lang::getCurrentLocale();

        elseif ( $localeName !== null )
            // Using Locale by provided locale name
            $translateTo = Locale::getLocaleKey( $localeName );

        elseif ( $localeKey !== null && Locale::checkLocaleKey( $localeKey ) )
            // Using Locale by provided locale key
            $translateTo = $localeKey;

        else
            throw new RuntimeException( 'Invalid locale name or key!' );


        // Check if locale is available for translation
        if ( in_array( $translateTo, LANGUAGES_LIST, true ) === false )
            throw new InvalidConfigurationException( sprintf( 'Locale "%s" is not supported!', Locale::getLocaleName( $translateTo ) ) );

        if ( count( $object ) === 0 )
            throw new RuntimeException( 'Empty translation object!' );


        // Return translation for current or provided locale if exists
        if ( array_key_exists( $translateTo, $object ) )
            return $object[ $translateTo ];

        // Return translation for default locale if exists
        if ( array_key_exists( DEFAULT_LOCALE, $object ) ) {

            // Return translation for default locale if exists (DEV & TEST ENV ONLY)
            if ( env( 'app_env' ) !== env( 'PROD_ENV' ) ) {
//                TODO:: Create log
//                trigger_error(
//                    sprintf(
//                        'Array «%s» missing translation for locale "%s"! Using default locale "%s" translation...',
//                        j_encode( $object ), Locale::getLocaleName( $translateTo ), Locale::getLocaleName( DEFAULT_LOCALE )
//                    ), E_USER_WARNING
//                );

                return $object[ array_key_first( $object ) ] . ' (not translated to ' . Locale::getLocaleName( $translateTo ) . ')';
            }

            return $object[ DEFAULT_LOCALE ];
        }

        // Return first translation from array (DEV & TEST ENV ONLY)
        if ( env( 'app_env' ) !== env( 'PROD_ENV' ) ) {
//            TODO:: Create log
//            trigger_error(
//                sprintf(
//                    'Array «%s» missing translation for locale "%s"! Using first array key translation...',
//                    j_encode( $object ), Locale::getLocaleName( $translateTo )
//                ), E_USER_WARNING
//            );

            return $object[ array_key_first( $object ) ] . ' (not translated to ' . Locale::getLocaleName( $translateTo ) . ')';
        }

        // Return first translation from array
        return $object[ array_key_first( $object ) ];
    }


    public static function isSaveEnabled(): bool
    {
        return self::$saveEnabled;
    }

    private function addTranslateStringToLocaleFile( string $string, bool $recursion = true ): void
    {
        foreach ( $this as $locale => $arr )
            $this->$locale[ $string ] = $string . ' (not_translated)';


        $this->saveLocalesToFiles();

        foreach ( $this as $locale => $arr )
            foreach ( self::$allTranslationsKeys as $key => $value )
                if ( !array_key_exists( $key, $this->$locale ) )
                    $this->$locale[ $key ] = $value;


        if ( $recursion )
            $this->addTranslateStringToLocaleFile( $string, false );
    }

    /**
     * @noinspection PhpVariableVariableInspection
     */
    private function saveLocalesToFiles(): void
    {
        foreach ( self::getTranslationDirectories() as $translationDirectory ) {
            if ( !str_contains( $translationDirectory, 'Platform/src' ) ) {
                foreach ( $this as $locale => $arr ) {
                    ksort( $this->$locale );

                    unlink( ( $path = sprintf( '%s/%s.php', $translationDirectory, $locale ) ) );

                    fwrite(
                        ( $file = fopen( $path, 'wb' ) ),
                        '<?php /** @noinspection ALL @formatter::off */ return [' . PHP_EOL
                    );

                    $previousLetter = '';
                    foreach ( $this->$locale as $translateString => $translation ) {
                        if ( empty( $translateString ) )
                            continue;


                        self::$allTranslationsKeys[ $translateString ] = "$translateString (not_translated)";

                        if ( $previousLetter !== $translateString[0] ) {
                            fwrite( $file, '//' . PHP_EOL . '//' . PHP_EOL );
                            $previousLetter = $translateString[0];
                        }

                        $translateString = str_replace( "'", "\'", $translateString );
                        $translation = str_replace( "'", "\'", $translation );
                        if ( 13 + mb_strlen( $translateString, 'UTF-8' ) + mb_strlen( $translation, 'UTF-8' ) > 120)
                            fwrite( $file, "    '$translateString' " . PHP_EOL ."    => '$translation'," . PHP_EOL );
                        else
                            fwrite( $file, "    '$translateString' => '$translation'," . PHP_EOL );
                    }

                    fwrite( $file, '//' . PHP_EOL . '];' );

                    fclose( $file );
                }
            }
        }
    }

    /**
     * @noinspection PhpIllegalStringOffsetInspection
     */
    public function updateTranslatedString( string $locale, string $key, string $translation ): string
    {
        $previousValue         = $this->$locale[ $key ];
        $this->$locale[ $key ] = $translation;

        $this->saveLocalesToFiles();

        return $previousValue;
    }

}
