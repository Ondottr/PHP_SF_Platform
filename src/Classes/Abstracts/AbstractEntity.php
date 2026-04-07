<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\Proxy;
use JsonSerializable;
use PHP_SF\System\Core\DoctrineCallbacksLoader;
use PHP_SF\System\Traits\EntityRepositoriesTrait;
use PHP_SF\System\Traits\ModelProperty\ModelPropertyIdTrait;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Translator as SymfonyTranslator;
use Symfony\Component\Validator\Validation;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractEntity extends DoctrineCallbacksLoader implements JsonSerializable
{

    use ModelPropertyIdTrait;
    use EntityRepositoriesTrait;


    private static array $entitiesList     = [];
    private array        $validationErrors = [];


    final public static function new(): static
    {
        return new static();
    }


    public static function clearQueryBuilderCache(): void
    {
        ca()->deleteByKeyPattern( '*doctrine_result_cache:*' );
    }

    final public function validate(): bool
    {
        $this->validationErrors = [];

        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setTranslator( self::validatorTranslator() )
            ->setTranslationDomain( 'validators' )
            ->getValidator()
            ->validate( $this );

        foreach ( $violations as $violation ) {
            $pName = $violation->getPropertyPath();

            $this->validationErrors[ $pName ] = _t( 'entity.field_validation_error', [
                'field'   => '@:' . $this->getTranslatablePropertyName( $pName ),
                'message' => rtrim( $violation->getMessage(), '.' ),
            ] );
        }

        return empty( $this->validationErrors );
    }

    final public function getTranslatablePropertyName( string $propertyName ): string
    {
        $rc = new ReflectionClass( static::class );

        return camel_to_snake( $rc->getShortName() ) . '.fields.' . camel_to_snake( $propertyName );
    }


    final public function getValidationErrors(): array|bool
    {
        if ( empty( $this->validationErrors ) )
            return true;

        return $this->validationErrors;
    }


    final public function jsonSerialize(): array|int
    {
        if ( $this instanceof Proxy )
            return $this->id;

        $arr = [];

        $reflectionClass = new ReflectionClass( static::class );

        $properties = [];
        foreach ( $reflectionClass->getProperties( ReflectionProperty::IS_PROTECTED ) as $ReflectionProperty )
            $properties[] = $ReflectionProperty->getName();

        foreach ( $properties as $property )
            $arr[ $property ] = ( $this->$property instanceof self ) ? $this->$property->getId() : $this->$property;

        return $arr;
    }


    /**
     * Returns a Symfony Translator loaded with the validator constraint translations
     * for the current DEFAULT_LOCALE. Falls back to English if the locale XLF is absent.
     * Cached for the lifetime of the process (static local variable).
     */
    private static function validatorTranslator(): SymfonyTranslator
    {
        static $translator = null;

        if ( $translator !== null ) {
            return $translator;
        }

        $locale          = DEFAULT_LOCALE;
        $translator      = new SymfonyTranslator( $locale );
        $loader          = new XliffFileLoader();

        $translator->addLoader( 'xlf', $loader );

        // symfony/validator ships its translations under Resources/translations/
        // relative to its own root; derive it from the Validation class file path.
        $translationsDir = dirname( ( new ReflectionClass( Validation::class ) )->getFileName() )
            . '/Resources/translations';

        $localXlf = $translationsDir . '/validators.' . $locale . '.xlf';

        if ( file_exists( $localXlf ) ) {
            $translator->addResource( 'xlf', $localXlf, $locale, 'validators' );
        } else {
            // Locale not bundled — fall back to English
            $enXlf = $translationsDir . '/validators.en.xlf';
            if ( file_exists( $enXlf ) ) {
                $translator->addResource( 'xlf', $enXlf, 'en', 'validators' );
                $translator->setLocale( 'en' );
            }
        }

        return $translator;
    }

}
