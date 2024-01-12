<?php declare( strict_types=1 );

namespace PHP_SF\System\Core;

use PHP_SF\System\Attributes\Validator\TranslatablePropertyName;
use PHP_SF\System\Classes\Exception\InvalidEntityConfigurationException;
use PHP_SF\System\Classes\Exception\UndefinedLocaleKeyException;
use PHP_SF\System\Classes\Exception\UndefinedLocaleNameException;
use PHP_SF\System\Classes\Helpers\Locale;
use PHP_SF\System\Database\DoctrineEntityManager;
use ReflectionClass;
use ReflectionProperty;
use ReflectionUnionType;
use RuntimeException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use function array_key_exists;

#[\AllowDynamicProperties]
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

                if ( ( $translations = ca()->get( $redisKey ) ) === null ) {
                    if ( file_exists( ( $path = sprintf( '%s/%s.php', $translationDirectory, $locale ) ) ) === false )
                        // @formatter::off
                        file_put_contents( $path, <<<'EOF'
<?php /** @noinspection ALL @formatter::off */ return [
/**
* This file was automatically generated after adding new translation language to your project.
* You can add new translated strings in format below or use {@link _t()} function.
* "string_for_translation" => "Translation for string with arguments: %s, %s"
* All provided strings in {@link _t()} function will be automatically added to this and another locale translation files
*/
];
EOF
                        ); // @formatter::on

                    elseif ( isset( $this->$locale ) && !empty( $this->$locale ) )
                        $this->$locale = array_merge( $this->$locale, ( require $path ) );

                    else
                        $this->$locale = require $path;


                    if ( DEV_MODE === false )
                        rp()->set( $redisKey, json_encode( $this->$locale ) );

                } else
                    $this->$locale = json_decode( $translations, true, 512, JSON_THROW_ON_ERROR );

            }
        }

        if ( DEV_MODE )
            $this->saveTranslatablePropertyNames();
    }

    private static function getTranslationDirectories(): array
    {
        return self::$translationDirectories;
    }

    public static function getInstance(): self
    {
        if ( isset( self::$translator ) === false )
            return self::setInstance();


        return self::$translator;
    }

    private static function setInstance(): self
    {
        return self::$translator = new self;
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
        if ( self::isSaveEnabled() &&
            array_key_exists( $string, $this->{Lang::getCurrentLocale()} ) === false
        )
            $this->addTranslateStringToLocaleFile( $string );


        return sprintf( ( $this->{Lang::getCurrentLocale()} )[ $string ] ?? $string, ...$values );
    }

    /**
     * Returns translation from provided object or array and for current or provided locale
     *
     * Select localeKey from {@link Locale}
     * using methods {@link Locale::getLocaleKey()} and {@link Locale::getLocaleName()}
     *
     * Array or object must be in format:
     *
     * <code>[ "en" => "English translation", "bg" => "Bulgarian translation" ]</code>
     *
     * <code>{ "en": "English translation", "bg": "Bulgarian translation" }</code>
     *
     * @throws UndefinedLocaleKeyException|UndefinedLocaleNameException
     * @throws InvalidConfigurationException if provided locale is not supported ( {@link LANGUAGES_LIST} )
     * @throws RuntimeException if provided object or array is not in correct format or empty
     */
    public function translateFromArray( array|object $object, string|null $localeName = null, string|null $localeKey = null ): string|array|object {
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
            throw new InvalidConfigurationException(
                sprintf( 'Locale "%s" is not supported!', Locale::getLocaleName( $translateTo ) )
            );

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
                        $translation     = str_replace( "'", "\'", $translation );
                        if ( 13 + mb_strlen( $translateString, 'UTF-8' ) + mb_strlen( $translation, 'UTF-8' ) > 120 )
                            fwrite( $file, "    '$translateString' " . PHP_EOL . "    => '$translation'," . PHP_EOL );
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

    /**
     * Gets all translatable properties from all entities and saves them to locale files
     */
    private function saveTranslatablePropertyNames(): void
    {
        $entities          = [];
        $entityDirectories = DoctrineEntityManager::getEntityDirectories();

        // Get all entities from entity directories
        foreach ( $entityDirectories as $dir ) {
            // Get all files in entity directory
            $entitiesFromDirectory = array_diff( scandir( $dir ), [ '.', '..' ] );

            foreach ( $entitiesFromDirectory as $file ) {
                // Get full path to file
                $path = "$dir/$file";

                // Throw exception if directory found
                if ( is_dir( $path ) )
                    throw new RuntimeException( 'You can\'t have directories in entity directory!' );

                // Get file name without extension
                $array1   = explode( '/', $path );
                $fileName = str_replace( '.php', '', ( end( $array1 ) ) );
                // Get namespace
                $var       = explode( 'namespace ', file_get_contents( $path ) );
                $namespace = explode( ';', $var[1], 2 )[0];

                // Add entity to array
                $entities[] = "$namespace\\$fileName";
            }
        }

        // Get all translatable properties from entities and save them to locale files
        foreach ( $entities as $entity ) {
            $rc = new ReflectionClass( $entity );
            /**
             * Get all protected properties
             *
             * Because we don't want to translate private properties
             *
             * Private properties are used for OneToMany and ManyToOne relations
             */
            $properties = $rc->getProperties( ReflectionProperty::IS_PROTECTED );

            foreach ( $properties as $property ) {
                // Id property is not translatable
                if ( $property->getName() === 'id' )
                    continue;
                // Boolean properties translation are optional
                if ( $property->getType() instanceof ReflectionUnionType === false && $property->getType()->getName() === 'bool' )
                    continue;

                $attributes = $property->getAttributes( TranslatablePropertyName::class );
                $ac         = count( $attributes );

                // Throw exception if attribute is missing
                if ( $ac === 0 )
                    throw new InvalidEntityConfigurationException(
                        sprintf(
                            'The required attribute "PHP_SF\System\Attributes\Validator\TranslatablePropertyName" is missing in the property "%s" of the entity "%s".',
                            $property->getName(), $entity
                        )
                    );

                // Throw exception if attribute is defined multiple times
                if ( $ac > 1 )
                    throw new InvalidEntityConfigurationException(
                        sprintf(
                            'The attribute "PHP_SF\System\Attributes\Validator\TranslatablePropertyName" is defined multiple times in the property "%s" of the entity "%s".',
                            $property->getName(), $entity
                        )
                    );

                $args = $attributes[0]->getArguments();

                // Throw exception if attribute is defined without arguments
                if ( empty( $args ) )
                    throw new InvalidEntityConfigurationException(
                        sprintf(
                            'The attribute "PHP_SF\System\Attributes\Validator\TranslatablePropertyName" is defined without arguments in the property "%s" of the entity "%s".',
                            $property->getName(), $entity
                        )
                    );

                if ( array_key_exists( 'name', $args ) )
                    $string = $args['name'];
                elseif ( array_key_exists( 0, $args ) )
                    $string = $args[0];
                else
                    // Throw exception if attribute is defined without correct arguments
                    throw new InvalidEntityConfigurationException(
                        sprintf(
                            'The attribute "PHP_SF\System\Attributes\Validator\TranslatablePropertyName" is defined without correct arguments in the property "%s" of the entity "%s".',
                            $property->getName(), $entity
                        )
                    );

                // Add string to array if it doesn't exist
                if ( array_key_exists( $string, $this->{Lang::getCurrentLocale()} ) === false )
                    $this->addTranslateStringToLocaleFile( $string );

            }
        }
    }

}
