<?php
declare( strict_types=1 );

namespace PHP_SF\System\Core;

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
                        file_put_contents(
                            $path,
                            '<?php /** @noinspection ALL @formatter::off */ return [
/**
* This file was automatically generated
* You can add new translated strings in format below or use {@see _t()} function.
* "string_for_translation" string => "Translation for string with arguments: %s, %s"
* All provided strings in {@see _t()} function will be automatically added to this and another locale translation files
*/
];'
                        ); // @formatter::on

                    elseif ( isset( $this->$locale ) && !empty( $this->$locale ) )
                        /** @noinspection SlowArrayOperationsInLoopInspection */
                        $this->$locale = array_merge( $this->$locale, ( require $path ) );

                    else
                        $this->$locale = require $path;


                    if ( DEV_MODE === false )
                        rp()->set( $redisKey, json_encode( $this->$locale ) );

                }
                else
                    $this->$locale = json_decode( $translations, true, 512, JSON_THROW_ON_ERROR );

            }
        }
    }

    private static function getTranslationDirectories(): array
    {
        return self::$translationDirectories;
    }

    public static function getInstance(): Translator
    {
        if ( !isset( self::$translator ) )
            return self::setInstance();


        return self::$translator;
    }

    private static function setInstance(): Translator
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

                        $translation = str_replace( "'", "\'", $translation );
                        fwrite( $file, "    '$translateString' => '$translation'," . PHP_EOL );
                    }

                    fwrite( $file, '//' . PHP_EOL . '];' );

                    fclose( $file );
                }
            }
        }
    }

    public function updateTranslatedString( string $locale, string $key, string $translation ): string
    {
        $previousValue = $this->$locale[ $key ];
        $this->$locale[ $key ] = $translation;

        $this->saveLocalesToFiles();

        return $previousValue;
    }
}
