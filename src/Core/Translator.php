<?php declare( strict_types=1 );

namespace PHP_SF\System\Core;

use AllowDynamicProperties;
use PHP_SF\System\Attributes\Validator\TranslatablePropertyName;
use PHP_SF\System\Classes\Exception\InvalidEntityConfigurationException;
use PHP_SF\System\Classes\Helpers\Locale;
use PHP_SF\System\Database\DoctrineEntityManager;
use ReflectionClass;
use ReflectionProperty;
use ReflectionUnionType;
use RuntimeException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use function array_key_exists;


/**
 * Class Translator
 *
 * Class is responsible for managing translations in the application.
 * It loads translations from specified directories, provides methods for
 * translating strings, and handles the addition of new translation strings
 * to locale files.
 *
 * @package PHP_SF\System\Core
 * @author  Dmytro Dyvulskyi <dmytro.dyvulskyi@nations-original.com>
 */
#[AllowDynamicProperties]
final class Translator
{

    /**
     * An array storing all translation keys.
     */
    private static array $allTranslationsKeys    = [];
    /**
     * The singleton instance of the Translator class.
     */
    private static self $instance;
    /**
     * An array storing directories where translation files are located.
     */
    private static array $translationDirectories = [];


    /**
     * Loads translations on creation.
     */
    private function __construct()
    {
        $this->loadTranslation();
    }


    /**
     * Returns the singleton instance of the Translator class.
     * If no instance exists, create a new one.
     *
     * @return self The singleton instance.
     */
    public static function getInstance(): self
    {
        if ( isset( self::$instance ) === false )
            return self::setInstance();


        return self::$instance;
    }

    /**
     * Adds a new directory to the list of translation directories.
     *
     * @param string $translationDirectory The directory to add.
     */
    public static function addTranslationDirectory( string $translationDirectory ): void
    {
        self::$translationDirectories[] = $translationDirectory;
    }


    /**
     * Returns all translations for all supported languages.
     *
     * @return array<string, string<string, string>> An array of translations.
     */
    public function getTranslations(): array
    {
        foreach ( LANGUAGES_LIST as $langKey ) {
            $translations[ $langKey ] = $this->$langKey;
        }

        return $translations;
    }

    /**
     * Translates a given string to the current locale.
     * If the string does not exist in the locale file, adds it.
     *
     * @param string $string    The string to translate.
     * @param mixed  ...$values Values to format the translated string.
     *
     * @return string The translated string.
     */
    public function translate( string $string, ...$values ): string
    {
        if ( array_key_exists( $string, $this->{Lang::getCurrentLocale()} ) === false ) {
            $this->addTranslateStringToLocaleFile( $string );
        }

        return sprintf( ( $this->{Lang::getCurrentLocale()} )[ $string ] ?? $string, ...$values );
    }

    /**
     * Translates a given string to a specified locale.
     * If the string does not exist in the locale file, adds it.
     *
     * @param string $string      The string to translate.
     * @param string $translateTo The locale to translate to.
     * @param mixed  ...$values   Values to format the translated string.
     *
     * @return string The translated string.
     * @throws InvalidConfigurationException If the specified locale is not supported.
     */
    public function translateTo( string $string, string $translateTo, ...$values ): string
    {
        // Check if the provided locale is supported
        if ( in_array( $translateTo, LANGUAGES_LIST, true ) === false ) {
            throw new InvalidConfigurationException(
                sprintf( 'Locale "%s" is not supported!', Locale::getLocaleName( $translateTo ) )
            );
        }

        // Add the string to the locale file if it does not exist
        if ( array_key_exists( $string, $this->$translateTo ) === false ) {
            $this->addTranslateStringToLocaleFile( $string );
        }

        // Return the translated string or the original string if translation does not exist
        return sprintf( ( $this->$translateTo[ $string ] ?? $string ), ...$values );
    }


    /**
     * Returns the list of translation directories.
     *
     * @return array The list of translation directories.
     */
    private static function getTranslationDirectories(): array
    {
        return self::$translationDirectories;
    }

    /**
     * Creates and sets the singleton instance of the Translator class.
     *
     * @return self The newly created instance.
     */
    private static function setInstance(): self
    {
        return self::$instance = new self;
    }


    /**
     * Adds a translation string to locale files if it does not already exist.
     * Optionally, handles recursion for nested translations.
     *
     * @param string $string    The string to add.
     * @param bool   $recursion Whether to handle recursion.
     */
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
     * Saves all locale translations to their respective files.
     */
    private function saveLocalesToFiles(): void
    {
        foreach ( self::getTranslationDirectories() as $translationDirectory ) {
            if ( !str_contains( $translationDirectory, 'vendor/nations-original' ) ) {
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
     * Gets all translatable properties from all entities and saves them to locale files.
     *
     * @throws InvalidEntityConfigurationException If entity configuration is invalid.
     * @throws RuntimeException If a directory is found in the entity directory.
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

    /**
     * Loads translations from the specified directories.
     * Throws an exception if no directories are specified.
     *
     * @throws InvalidConfigurationException If no translation directories are specified.
     */
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
                        file_put_contents(
                            $path,
                            <<<'EOF'
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

}
