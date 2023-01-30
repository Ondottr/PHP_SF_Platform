<?php declare( strict_types=1 );

namespace PHP_SF\System\Core;

use http\Exception\RuntimeException;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Immutable;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function chr;
use function in_array;
use function is_array;

final class TemplatesCache
{

    private static self $instance;

    #[Immutable( Immutable::CONSTRUCTOR_WRITE_SCOPE )]
    private static array $templatesDefinition = [];

    private static array $templatesNamespaces = [];
    private static array $templatesDirectories = [];


    private function __construct()
    {
        foreach ( $this->getTemplatesDirectories() as $dir )
            if ( file_exists( ( $path = __DIR__ . '/../../../' . $dir ) ) === false || is_dir( $path ) === false )
                throw new InvalidConfigurationException( sprintf( 'Invalid template directory “%s”', $dir ) );


        self::$templatesDefinition = array_combine( $this->getTemplatesDirectories(), $this->getTemplatesNamespaces() );
    }

    private function getTemplatesDirectories(): array
    {
        return self::$templatesDirectories;
    }

    private function getTemplatesNamespaces(): array
    {
        return self::$templatesNamespaces;
    }

    public static function addTemplatesNamespace( string ...$templateNamespaces ): void
    {
        self::$templatesNamespaces = array_merge( self::$templatesNamespaces, $templateNamespaces );
    }

    public static function addTemplatesDirectory( string ...$templateDirectories ): void
    {
        self::$templatesDirectories = array_merge( self::$templatesDirectories, $templateDirectories );
    }

    public static function getInstance(): self
    {
        if ( isset( self::$instance ) === false )
            self::setInstance();

        return self::$instance;
    }

    private static function setInstance(): void
    {
        self::$instance = new self;
    }

    #[ArrayShape( [ 'fileName' => 'string', 'className' => 'string' ] )]
    public function getCachedTemplateClass( string $className ): array|false
    {
        $newNamespace = 'PHP_SF\CachedTemplates';
        if ( TEMPLATES_CACHE_ENABLED === false || str_contains( $className, $newNamespace ) )
            return false;


        foreach ( $this->getTemplatesDefinition() as $directory => $namespace ) {
            if ( str_contains( $className, $namespace ) === false )
                continue;


            $arr = ( explode( '\\', $className ) );
            array_pop( $arr );
            $currentNamespace = implode( '\\', $arr );
            $newClassName = str_replace( $namespace, $newNamespace, $className );

            $newFileDirectory = sprintf( '/tmp/%s/%s.php',
                env( 'SERVER_PREFIX' ), str_replace( '\\', '/', $newClassName )
            );
            $arr = explode( '/', $newFileDirectory );
            $fileName = array_pop( $arr );
            $newFileDirectory = implode( '/', $arr );

            $currentClassDirectory = sprintf( '%s/../../../%s/%s.php', __DIR__, $directory,
                str_replace( [ $namespace, '\\' ], [ '', '/' ], $className )
            );
        }

        if ( isset( $newClassName, $currentClassDirectory ) === false )
            return false;


        if ( ( file_exists( $newFileDirectory ) === false ) &&
            mkdir( $newFileDirectory, recursive: true ) === false && is_dir( $newFileDirectory ) === false
        )
            throw new RuntimeException( _t( 'Directory “%s” was not created!', $newFileDirectory ) );


        $fileContent = $this->removeComments( $currentClassDirectory );

        foreach ( $this->getTemplatesNamespaces() as $oldNamespace ) {
            $fileContent = str_replace( "namespace $oldNamespace", "namespace $newNamespace", $fileContent );

            $imports = explode( '$this->import(', $fileContent );
            unset( $imports[0] );
            foreach ( $imports as $str ) {
                $importedView = trim( explode( '::class', $str )[0] );

                if ( str_contains( $importedView, '\\' ) === false &&
                    str_contains( $fileContent, sprintf( '\%s;', $importedView ) ) === false
                ) {
                    $fileContent = str_replace(
                        [ sprintf( '$this->import(%s', $importedView ), sprintf( '$this->import( %s', $importedView ) ],
                        sprintf( '$this->import(\%s\%s', $currentNamespace, $importedView ),
                        $fileContent
                    );
                }
            }
        }

        //remove redundant characters
        $replace = [
            // Remove JS inline comments
            '/\/\/.*$/m' => '',
            //remove HTML comments
            '/<!--(.|\s)*?-->/' => '',
            //remove HTML comments
            '/\s+/' => ' ',
            //remove tabs before and after HTML tags
            '/\>[^\S ]+/s' => '>',
            '/[^\S ]+\</s' => '<',
            //shorten multiple whitespace sequences; keep new-line characters because they matter in JS!!!
            '/([\t ])+/s' => ' ',
            //remove leading and trailing spaces
            '/^([\t ])+/m' => '',
            '/([\t ])+$/m' => '',
            // remove JS line comments (simple only); do NOT remove lines containing URL (e.g. 'src="http://server.com/"')!!!
            '~//[a-zA-Z0-9 ]+$~m' => '',
            //remove empty lines (sequence of line-end and white-space characters)
            '/[\r\n]+([\t ]?[\r\n]+)+/s' => "\n",
            //remove empty lines (between HTML tags); cannot remove just any line-end characters because in inline JS they can matter!
            '/\>[\r\n\t]+\</s' => '><',
            //remove "empty" lines containing only JS's block end character; join with next line (e.g. "}\n}\n</script>" --> "}}</script>"
            '/}[\r\n\t ]+,[\r\n\t ]+/s' => '},',
            //remove new-line after JS's function or condition start; join with next line
            '/\)[\r\n\t ]?{[\r\n\t ]+/s' => '){',
            '/,[\r\n\t ]?{[\r\n\t ]+/s' => ',{',
            //remove new-line after JS's line end (only most obvious and safe cases)
            '/\),[\r\n\t ]+/s' => '),',
            //remove quotes from HTML attributes that does not contain spaces; keep quotes around URLs!
            //$1 and $4 insert first white-space character found before/after attribute
            '~([\r\n\t ])?([a-zA-Z0-9]+)="([a-zA-Z0-9_/\\-]+)"([\r\n\t ])?~s' => '$1$2=$3$4',
            '/(\n|^)(\x20+|\t)/' => "\n",
            '/(\n|^)\/\/(.*?)(\n|$)/' => "\n",
            '/\n/' => ' ',
            '/<!--.*?-->/' => '',
            '/(\x20+|\t)/' => ' ', # Delete multispace (Without \n)
            '/(["\'])\s+>/' => "$1>", # strip whitespaces between quotation ("') and end tags
            '/=\s+(["\'])/' => "=$1", # strip whitespaces between = "'
            '/ {2,}/' => ' ', # Shorten multiple whitespace sequences
            '/<!--.*?-->|\t|(?:\r?\n[ \t]*)+/s' => '',
            '/>[^\S ]+/' => '>', # strip whitespaces after tags, except space
            '/[^\S ]+</' => '<', # strip whitespaces before tags, except space
            '/(\s)+/' => '\\1', # shorten multiple whitespace sequences
        ];
        $fileContent = preg_replace( array_keys( $replace ), array_values( $replace ), $fileContent );

        // remove optional ending tags {@link http://www.w3.org/TR/html5/syntax.html#syntax-tag-omission}
        $remove = [ '</option>', '</li>', '</dt>', '</dd>', '</tr>', '</th>', '</td>', ];
        $fileContent = trim( str_ireplace( $remove, '', $fileContent ) );

        $newFileDirectory = sprintf( '%s/%s', $newFileDirectory, $fileName );

        if ( DEV_MODE === true || !file_exists( $newFileDirectory ) )
            file_put_contents( $newFileDirectory, $fileContent );

        return [ 'className' => $newClassName, DEV_MODE === true, 'fileName' => $newFileDirectory ];
    }

    public function getTemplatesDefinition(): array
    {
        return self::$templatesDefinition;
    }

    private function removeComments( string $filename ): string
    {
        $w = [ ';', '{', '}' ];
        $ts = token_get_all( php_strip_whitespace( $filename ) );
        $s = '';

        foreach ( $ts as $t ) {
            if ( is_array( $t ) )
                $s .= $t[1];

            else {
                $s .= $t;
                if ( in_array( $t, $w, true ) )
                    $s .= chr( 13 ) . chr( 10 );

            }
        }

        return $s;
    }
}
