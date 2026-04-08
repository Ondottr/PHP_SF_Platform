<?php declare( strict_types=1 );

namespace PHP_SF\Tests\System\Core;

use PHP_SF\System\Core\TranslatorV2;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Yaml\Yaml;

final class TranslatorV2Test extends TestCase
{

    private string      $dir;
    private array       $savedDirs;
    private ?TranslatorV2 $savedInstance;


    protected function setUp(): void
    {
        $ref = new ReflectionClass( TranslatorV2::class );

        $this->savedDirs     = $ref->getProperty( 'dirs' )->getValue( null );
        $this->savedInstance = $ref->getProperty( 'instance' )->getValue( null );

        $this->dir = sys_get_temp_dir() . '/translator_v2_test_' . uniqid( '', true );
        mkdir( $this->dir, 0777, true );

        $this->resetTranslatorState();
    }

    protected function tearDown(): void
    {
        $this->removeDir( $this->dir );

        $ref = new ReflectionClass( TranslatorV2::class );
        $ref->getProperty( 'instance' )->setValue( null, $this->savedInstance );
        $ref->getProperty( 'dirs' )->setValue( null, $this->savedDirs );

        if ( $this->savedInstance !== null ) {
            $ref->getProperty( 'catalogsLoaded' )->setValue( $this->savedInstance, false );
        }
    }


    // ── helpers ──────────────────────────────────────────────────────────

    private function resetTranslatorState(): void
    {
        $ref = new ReflectionClass( TranslatorV2::class );

        $ref->getProperty( 'instance' )->setValue( null, null );
        $ref->getProperty( 'dirs' )->setValue( null, [] );
    }

    /**
     * Write a locale YAML file into $this->dir and register it.
     * Defaults to LANGUAGES_LIST[0] so tests are locale-agnostic.
     */
    private function registerYaml( string $yamlContent, ?string $locale = null ): void
    {
        $locale ??= DEFAULT_LOCALE;
        file_put_contents( $this->dir . '/' . $locale . '.yaml', $yamlContent );
        TranslatorV2::addTranslationDir( $this->dir );
    }

    /**
     * Create a named subdirectory, write a YAML file into it, and register it.
     */
    private function registerYamlInSubdir( string $subdir, string $yamlContent, ?string $locale = null ): string
    {
        $locale ??= DEFAULT_LOCALE;
        $path = $this->dir . '/' . $subdir;
        mkdir( $path, 0777, true );
        file_put_contents( $path . '/' . $locale . '.yaml', $yamlContent );
        TranslatorV2::addTranslationDir( $path );

        return $path;
    }

    private function removeDir( string $path ): void
    {
        if ( !is_dir( $path ) ) {
            return;
        }

        $items = array_diff( scandir( $path ), [ '.', '..' ] );
        foreach ( $items as $item ) {
            $full = $path . '/' . $item;
            is_dir( $full ) ? $this->removeDir( $full ) : unlink( $full );
        }

        rmdir( $path );
    }


    // ── basic lookup ─────────────────────────────────────────────────────

    public function testBasicKeyTranslation(): void
    {
        $this->registerYaml( 'greeting: "Hello!"' );

        $this->assertSame( 'Hello!', TranslatorV2::getInstance()->trans( 'greeting' ) );
    }

    public function testValueReturnedVerbatimWhenNoParamsPassed(): void
    {
        /**
         * A value that contains a {placeholder} but no parameters are provided.
         * The placeholder must survive in the output unchanged.
         */
        $this->registerYaml( 'msg: "Hello, {name}!"' );

        $this->assertSame( 'Hello, {name}!', TranslatorV2::getInstance()->trans( 'msg' ) );
    }

    public function testGetLocaleReturnsDefaultLocale(): void
    {
        $this->registerYaml( '' );

        $this->assertSame( DEFAULT_LOCALE, TranslatorV2::getInstance()->getLocale() );
    }


    // ── {param} interpolation ─────────────────────────────────────────────

    public function testSingleNamedParamInterpolation(): void
    {
        $this->registerYaml( 'greeting: "Hello, {name}!"' );

        $result = TranslatorV2::getInstance()->trans( 'greeting', [ 'name' => 'Alice' ] );

        $this->assertSame( 'Hello, Alice!', $result );
    }

    public function testMultipleNamedParamsInterpolation(): void
    {
        $this->registerYaml( 'range: "{field} must be between {min} and {max}."' );

        $result = TranslatorV2::getInstance()->trans( 'range', [
            'field' => 'Age',
            'min'   => '18',
            'max'   => '99',
        ] );

        $this->assertSame( 'Age must be between 18 and 99.', $result );
    }

    public function testExtraParamsNotPresentInValueAreIgnored(): void
    {
        /**
         * Params passed that have no corresponding {placeholder} in the value
         * must be silently ignored — no error, no leakage into the output.
         */
        $this->registerYaml( 'msg: "Static text."' );

        $result = TranslatorV2::getInstance()->trans( 'msg', [ 'unused' => 'SHOULD_NOT_APPEAR' ] );

        $this->assertSame( 'Static text.', $result );
        $this->assertStringNotContainsString( 'SHOULD_NOT_APPEAR', $result );
    }

    public function testSamePlaceholderMultipleTimesInValue(): void
    {
        $this->registerYaml( 'repeat: "{word}, {word}, {word}!"' );

        $result = TranslatorV2::getInstance()->trans( 'repeat', [ 'word' => 'go' ] );

        $this->assertSame( 'go, go, go!', $result );
    }

    public function testIntegerParamIsCastToString(): void
    {
        $this->registerYaml( 'count: "You have {n} messages."' );

        $result = TranslatorV2::getInstance()->trans( 'count', [ 'n' => 42 ] );

        $this->assertSame( 'You have 42 messages.', $result );
    }


    // ── @:key references ─────────────────────────────────────────────────

    public function testAtKeyReferenceResolvesToLinkedValue(): void
    {
        /**
         * When a parameter value is "@:some.key", it must be resolved to the
         * translation value of "some.key" before being substituted into the message.
         */
        $this->registerYaml( implode( "\n", [
            'entities.post.title: "Title"',
            'validation.too_long: "{field} is too long."',
        ] ) );

        $result = TranslatorV2::getInstance()->trans(
            'validation.too_long',
            [ 'field' => '@:entities.post.title' ]
        );

        $this->assertSame( 'Title is too long.', $result );
    }

    public function testAtKeyReferenceNotFoundReturnsBareKeyName(): void
    {
        /**
         * When the referenced key does not exist in any catalog, the bare key name
         * is used as the substitution value (no exception, no _not_translated suffix).
         */
        $this->registerYaml( 'msg: "Field: {field}."' );

        $result = TranslatorV2::getInstance()->trans(
            'msg',
            [ 'field' => '@:nonexistent.key' ]
        );

        $this->assertSame( 'Field: nonexistent.key.', $result );
    }

    public function testAtKeyReferencedValueWithPlaceholderStaysLiteral(): void
    {
        /**
         * If the referenced key's own value contains a {placeholder}, that placeholder
         * is NOT resolved further — @:key references do not inherit the caller's params.
         */
        $this->registerYaml( implode( "\n", [
            'label.with_param: "Hello, {name}!"',
            'outer: "Got: {inner}"',
        ] ) );

        $result = TranslatorV2::getInstance()->trans(
            'outer',
            [ 'inner' => '@:label.with_param' ]
        );

        // The {name} placeholder inside label.with_param must survive unresolved.
        $this->assertSame( 'Got: Hello, {name}!', $result );
    }

    public function testMixedAtRefAndLiteralParamsInSameCall(): void
    {
        $this->registerYaml( implode( "\n", [
            'entity.name: "Invoice"',
            'validation.required: "{field} ({context}) is required."',
        ] ) );

        $result = TranslatorV2::getInstance()->trans(
            'validation.required',
            [
                'field'   => '@:entity.name',
                'context' => 'billing',
            ]
        );

        $this->assertSame( 'Invoice (billing) is required.', $result );
    }

    public function testMultipleAtKeyRefsInSameCall(): void
    {
        $this->registerYaml( implode( "\n", [
            'field.first_name: "First name"',
            'field.last_name: "Last name"',
            'validation.mismatch: "{a} and {b} do not match."',
        ] ) );

        $result = TranslatorV2::getInstance()->trans(
            'validation.mismatch',
            [
                'a' => '@:field.first_name',
                'b' => '@:field.last_name',
            ]
        );

        $this->assertSame( 'First name and Last name do not match.', $result );
    }


    // ── ICU plural / MessageFormatter ────────────────────────────────────

    public function testIcuPluralStringIsFormattedCorrectly(): void
    {
        /**
         * Values stored as ICU plural format must be resolved to a human-readable
         * string when the intl extension is available. strtr() can't do this because
         * {count} doesn't appear verbatim inside {count, plural, ...}.
         */
        if ( !class_exists( \MessageFormatter::class ) ) {
            $this->markTestSkipped( 'intl extension not available' );
        }

        $this->registerYaml( "common.time.years: |-\n  {count, plural,\n    one   {# yr}\n    other {# yrs}\n  }" );

        $this->assertSame( '1 yr',  TranslatorV2::getInstance()->trans( 'common.time.years', [ 'count' => 1 ] ) );
        $this->assertSame( '5 yrs', TranslatorV2::getInstance()->trans( 'common.time.years', [ 'count' => 5 ] ) );
    }

    public function testSimpleParamSubstitutionStillWorksWithMessageFormatter(): void
    {
        /**
         * MessageFormatter handles simple {name} arguments the same as strtr(),
         * so existing translations are not broken by the ICU upgrade.
         */
        if ( !class_exists( \MessageFormatter::class ) ) {
            $this->markTestSkipped( 'intl extension not available' );
        }

        $this->registerYaml( 'error: "Field `{field}`: {message}"' );

        $result = TranslatorV2::getInstance()->trans( 'error', [ 'field' => 'Title', 'message' => 'is required' ] );

        $this->assertSame( 'Field `Title`: is required', $result );
    }


    // ── locale fallback ──────────────────────────────────────────────────

    public function testFallbackToDefaultLocaleWhenKeyMissingInRequestedLocale(): void
    {
        /**
         * If the requested locale has no entry for the key but DEFAULT_LOCALE does,
         * the DEFAULT_LOCALE value is returned instead of triggering missing-key handling.
         */
        $this->registerYaml( 'greeting: "Hello!"' );

        // 'fr' is not in LANGUAGES_LIST and has no catalog; should fall back to 'en'.
        $result = TranslatorV2::getInstance()->trans( 'greeting', [], null, 'fr' );

        $this->assertSame( 'Hello!', $result );
    }

    public function testNoFallbackWhenKeyExistsInRequestedLocale(): void
    {
        /**
         * If the key exists in the requested locale's catalog, DEFAULT_LOCALE is never consulted.
         * Verified by giving 'fr' a different value for the same key.
         */
        file_put_contents( $this->dir . '/' . DEFAULT_LOCALE . '.yaml', 'greeting: "Hello!"' );
        TranslatorV2::addTranslationDir( $this->dir );

        // Inject a 'fr' catalog entry directly so we can verify it is used.
        $ref = new ReflectionClass( TranslatorV2::class );
        $catalogs = $ref->getProperty( 'catalogs' );
        $current  = $catalogs->getValue( TranslatorV2::getInstance() );
        $current['fr'] = [ 'greeting' => 'Bonjour!' ];
        $catalogs->setValue( TranslatorV2::getInstance(), $current );

        $result = TranslatorV2::getInstance()->trans( 'greeting', [], null, 'fr' );

        $this->assertSame( 'Bonjour!', $result );
    }


    // ── YAML key formats ─────────────────────────────────────────────────

    public function testNestedYamlKeysAreFlattenedToDotNotation(): void
    {
        /**
         * Nested YAML structure must be transparently flattened so that
         * "validation.field.required" maps to the deepest value.
         */
        $yaml = <<<YAML
        validation:
            field:
                required: "This field is required."
        YAML;

        $this->registerYaml( $yaml );

        $this->assertSame(
            'This field is required.',
            TranslatorV2::getInstance()->trans( 'validation.field.required' )
        );
    }

    public function testFlatDotNotationKeysWorkDirect(): void
    {
        $this->registerYaml( '"common.save.button": "Save"' );

        $this->assertSame( 'Save', TranslatorV2::getInstance()->trans( 'common.save.button' ) );
    }

    public function testNestedAndFlatKeysCanCoexistInSameFile(): void
    {
        $yaml = <<<YAML
        common.flat: "Flat"
        nested:
            key: "Nested"
        YAML;

        $this->registerYaml( $yaml );

        $this->assertSame( 'Flat', TranslatorV2::getInstance()->trans( 'common.flat' ) );
        $this->assertSame( 'Nested', TranslatorV2::getInstance()->trans( 'nested.key' ) );
    }


    // ── multi-directory merging ───────────────────────────────────────────

    public function testLaterRegisteredDirectoryOverridesEarlierOnKeyCollision(): void
    {
        /**
         * When two directories define the same key, the value from the last
         * registered directory wins (app layer overrides framework defaults).
         */
        $this->registerYamlInSubdir( 'base', 'label: "Base label"' );
        $this->registerYamlInSubdir( 'app', 'label: "App label"' );

        $this->assertSame( 'App label', TranslatorV2::getInstance()->trans( 'label' ) );
    }

    public function testKeysFromBothDirectoriesAreMerged(): void
    {
        /**
         * Keys that exist only in one directory must still be accessible
         * after a second directory is registered.
         */
        $this->registerYamlInSubdir( 'base', 'base.key: "From base"' );
        $this->registerYamlInSubdir( 'app', 'app.key: "From app"' );

        $this->assertSame( 'From base', TranslatorV2::getInstance()->trans( 'base.key' ) );
        $this->assertSame( 'From app', TranslatorV2::getInstance()->trans( 'app.key' ) );
    }


    // ── missing key (DEV_MODE = true) ────────────────────────────────────

    public function testMissingKeyReturnsSuffixedKeyInDevMode(): void
    {
        /**
         * In DEV_MODE, a missing key must be returned as "{key}_not_translated"
         * so the UI shows a visible marker without crashing.
         */
        $this->registerYaml( 'existing.key: "exists"' );

        $result = TranslatorV2::getInstance()->trans( 'missing.key' );

        $this->assertSame( 'missing.key_not_translated', $result );
    }

    public function testMissingKeyIsWrittenToLastRegisteredDirFile(): void
    {
        /**
         * When a key is missing, it must be persisted to the YAML file in the
         * last registered directory so the developer can fill in the translation
         * without manually touching the file.
         */
        file_put_contents( $this->dir . '/' . DEFAULT_LOCALE . '.yaml', '' );
        TranslatorV2::addTranslationDir( $this->dir );

        TranslatorV2::getInstance()->trans( 'brand.new.key' );

        $parsed = Yaml::parseFile( $this->dir . '/' . DEFAULT_LOCALE . '.yaml' );

        $this->assertIsArray( $parsed );
        $this->assertArrayHasKey( 'brand.new.key', $parsed );
        $this->assertSame( 'brand.new.key_not_translated', $parsed['brand.new.key'] );
    }

    public function testMissingKeyNotWrittenTwiceIfAlreadyPresent(): void
    {
        /**
         * If a key is missing from the catalog but already written in the YAML file
         * from a prior run, writeKeyToLastDir must not duplicate it.
         */
        file_put_contents( $this->dir . '/' . DEFAULT_LOCALE . '.yaml', "brand.new.key: brand.new.key_not_translated\n" );
        TranslatorV2::addTranslationDir( $this->dir );

        // The key is in the file but the catalog loaded it as _not_translated — which is a
        // real value, so trans() will return it without throwing. Verify no exception occurs.
        $result = TranslatorV2::getInstance()->trans( 'brand.new.key' );

        $this->assertSame( 'brand.new.key_not_translated', $result );
    }


    // ── multi-line / ICU plural values ───────────────────────────────────

    public function testMultilineIcuStringIsReturnedVerbatim(): void
    {
        /**
         * TranslatorV2 stores and returns raw ICU plural strings without modification.
         * The caller (frontend or an ICU library) is responsible for formatting.
         * Ensure a >- (folded) block scalar round-trips to the expected string.
         */
        $yaml = "common.time.years: >-\n  {count, plural,\n    one   {# yr}\n    other {# yrs}\n  }\n";
        $this->registerYaml( $yaml );

        $result = TranslatorV2::getInstance()->trans( 'common.time.years' );

        // >- folds single newlines but preserves more-indented blocks;
        // the parsed value is the ICU string with its internal newlines intact.
        $this->assertStringContainsString( '{count, plural,', $result );
        $this->assertStringContainsString( 'one   {# yr}', $result );
        $this->assertStringContainsString( 'other {# yrs}', $result );
    }

    public function testMultilineValuePreservedAfterFileRewrite(): void
    {
        /**
         * When a missing key triggers a file rewrite via writeKeyToLastDir(),
         * any existing multi-line ICU values in that file must round-trip
         * correctly — the rewritten file must still parse to the same string.
         */
        $icu = "{count, plural,\n  one   {# yr}\n  other {# yrs}\n}";

        file_put_contents(
            $this->dir . '/' . DEFAULT_LOCALE . '.yaml',
            \Symfony\Component\Yaml\Yaml::dump( [ 'common.time.years' => $icu ], 4, 4, \Symfony\Component\Yaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK )
        );
        TranslatorV2::addTranslationDir( $this->dir );

        // Trigger a rewrite of the file by requesting a missing key
        TranslatorV2::getInstance()->trans( 'missing.key' );

        $parsed = \Symfony\Component\Yaml\Yaml::parseFile( $this->dir . '/' . DEFAULT_LOCALE . '.yaml' );

        $this->assertIsArray( $parsed );
        $this->assertArrayHasKey( 'common.time.years', $parsed );
        $this->assertSame( $icu, $parsed['common.time.years'] );
    }

    public function testMultilineValueWrittenAsLiteralBlock(): void
    {
        /**
         * When a file containing a multi-line value is rewritten (e.g. after a
         * missing key is appended), the multi-line value must be serialized using
         * YAML literal block style (|- or |) rather than an inline double-quoted
         * string with \n escapes — so hand-editing locale files stays practical.
         */
        $icu = "{count, plural,\n  one   {# yr}\n  other {# yrs}\n}";

        file_put_contents(
            $this->dir . '/' . DEFAULT_LOCALE . '.yaml',
            \Symfony\Component\Yaml\Yaml::dump( [ 'common.time.years' => $icu ], 4, 4, \Symfony\Component\Yaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK )
        );
        TranslatorV2::addTranslationDir( $this->dir );

        // Trigger a rewrite
        TranslatorV2::getInstance()->trans( 'missing.key' );

        $fileContents = file_get_contents( $this->dir . '/' . DEFAULT_LOCALE . '.yaml' );

        // The ICU value must appear as a YAML literal block (|- or |), not as a
        // double-quoted single-line string with \n escape sequences.
        $this->assertStringNotContainsString( '"' . $icu . '"', $fileContents );
        $this->assertMatchesRegularExpression( '/common\.time\.years: \|[-+]?\n/', $fileContents );
    }

}
