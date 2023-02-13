<?php declare( strict_types=1 );

namespace PHP_SF\System\Core;

use App\Command\AppCacheClearCommand;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Immutable;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function chr;
use function in_array;
use function is_array;

/**
 * TemplatesCache is a class that provides caching functionality for HTML templates.
 * It's designed to reduce the time spent loading and parsing template files, which
 * can improve the overall performance of your application.
 * The class uses a file-based caching mechanism to store the compiled HTML templates.
 * Each time a template is requested, the cache is checked for a saved version of the
 * compiled template. If a cached version is found, it is returned immediately.
 * Otherwise, the original template file is loaded, compiled and saved to the cache
 * for future use.
 * The cache is not invalidated automatically. If you make changes to a template file,
 * you will need to delete the cached version of the file using the {@link AppCacheClearCommand}(a:c:c).
 * To use the class, simply pass the path to the template file to the getTemplate method.
 * The method will return the compiled HTML, which can then be used in your application.
 *
 * @author Dmytro Dyvulskyi
 * @copyright 2022 Nations Original sp. z o.o.
 */
final class TemplatesCache
{

    private const TEMPLATES_NAMESPACE = 'PHP_SF\\CachedTemplates';

    private static self $instance;

    #[Immutable( Immutable::CONSTRUCTOR_WRITE_SCOPE )]
    private static array $templatesDefinition = [];

    private static array $templatesNamespaces = [];
    private static array $templatesDirectories = [];


    /**
     * Constructor for TemplatesCache class.
     * This sets up the cache directory and other necessary parameters.
     *
     * @throws InvalidConfigurationException If a template directory does not exist
     */
    private function __construct()
    {
        foreach ( $this->getTemplatesDirectories() as $dir )
            if ( file_exists( ( $path = __DIR__ . '/../../../' . $dir ) ) === false || is_dir( $path ) === false )
                throw new InvalidConfigurationException( sprintf( 'Invalid template directory “%s”', $dir ) );


        self::$templatesDefinition = array_combine( $this->getTemplatesDirectories(), $this->getTemplatesNamespaces() );
    }

    /**
     * Get the list of templates directories.
     *
     * @return string[]
     */
    private function getTemplatesDirectories(): array
    {
        return self::$templatesDirectories;
    }

    /**
     * Get the list of templates namespaces.
     *
     * @return string[]
     */
    private function getTemplatesNamespaces(): array
    {
        return self::$templatesNamespaces;
    }

    /**
     * Add the templates namespaces to the list of templates namespaces.
     *
     * @param string ...$templateNamespaces
     */
    public static function addTemplatesNamespace( string ...$templateNamespaces ): void
    {
        self::$templatesNamespaces = [
            ...self::$templatesNamespaces,
            ...$templateNamespaces
        ];
    }

    /**
     * Add the templates directories to the list of templates directories.
     *
     * @param string ...$templateDirectories
     */
    public static function addTemplatesDirectory( string ...$templateDirectories ): void
    {
        self::$templatesDirectories = [
            ...self::$templatesDirectories,
            ...$templateDirectories
        ];
    }

    /**
     * Get the instance of the TemplatesCache class.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if ( isset( self::$instance ) === false )
            self::setInstance();

        return self::$instance;
    }

    /**
     * Set the instance of the TemplatesCache class.
     */
    private static function setInstance(): void
    {
        self::$instance = new self;
    }

    /**
     * Get the cached template class if it exists.
     *
     * @param string $className
     * @return string[]|false
     */
    #[ArrayShape( ['className' => 'string', 'fileContent' => 'string'] )]
    public function getCachedTemplateClass( string $className ): array|false
    {
        if ( TEMPLATES_CACHE_ENABLED === false || strpos( $className, self::TEMPLATES_NAMESPACE ) )
            return false;

        $cacheKey = sprintf( 'cached_template_class_%s', $className );

        if ( ca()->has( $cacheKey ) )
            return j_decode( ca()->get( $cacheKey ), true );


        foreach ( $this->getTemplatesDefinition() as $directory => $namespace ) {
            if ( strpos( $className, $namespace ) === false )
                continue;


            $arr = ( explode( '\\', $className ) );
            array_pop( $arr );
            $currentNamespace = implode( '\\', $arr );
            $newClassName = str_replace( $namespace, self::TEMPLATES_NAMESPACE, $className );

            $currentClassDirectory = sprintf(
                '%s/../../../%s/%s.php',
                __DIR__,
                $directory,
                str_replace( [ $namespace, '\\' ], [ '', '/' ], $className )
            );
        }

        if ( isset( $newClassName, $currentClassDirectory ) === false )
            return false;


        $fileContent = $this->removeComments( $currentClassDirectory );

        foreach ( $this->getTemplatesNamespaces() as $oldNamespace ) {
            $fileContent = str_replace( "namespace $oldNamespace", 'namespace ' . self::TEMPLATES_NAMESPACE, $fileContent );

            $imports = explode( '$this->import(', $fileContent );
            unset( $imports[0] );
            foreach ( $imports as $str ) {
                $importedView = trim( explode( '::class', $str )[0] );

                if ( strpos( $importedView, '\\' ) === false &&
                    strpos( $fileContent, sprintf( '\%s;', $importedView ) ) === false
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
            '/<!--.*?-->/' => '',
            '/(\x20+|\t)/' => ' ', # Delete multispace (Without \n)
            '/(["\'])\s+>/' => "$1>", # strip whitespaces between quotation ("') and end tags
            '/=\s+(["\'])/' => "=$1", # strip whitespaces between = "'
            '/ {2,}/' => ' ', # Shorten multiple whitespace sequences
            '/>[^\S ]+/' => '>', # strip whitespaces after tags, except space
            '/[^\S ]+</' => '<', # strip whitespaces before tags, except space
            '/(\s)+/' => '\\1', # shorten multiple whitespace sequences
        ];
        $fileContent = preg_replace( array_keys( $replace ), array_values( $replace ), $fileContent );

        /**
         * Replace all newline characters with spaces, but only if they are not within the <script> and </script> HTML tags.
         */
        // Split the input string $fileContent into an array of substrings using the string "script>" as the delimiter.
        $parts = explode( "script>", $fileContent );
        // Initialize $fileContent as an empty string.
        $fileContent = '';

        // Check if the number of elements in the $parts array is greater than 1.
        if ( count( $parts ) > 1 ) {
            // If there are multiple elements, iterate through each element in $parts.
            foreach ( $parts as $key => $part ) {
                // Check if the last two characters of the current element are equal to "</".
                if ( substr( $part, -2 ) !== '</' )
                    // If they are not equal, replace all newline characters in the current element with spaces.
                    $fileContent .= str_replace( "\n", " ", $part );

                else
                    // If the last two characters are equal to "</", concatenate the unmodified current element to $fileContent.
                    $fileContent .= $part;

                // If the current key is less than the total number of elements minus 1, concatenate "script> " to $fileContent.
                if ( $key < count( $parts ) - 1 )
                    $fileContent .= 'script> ';

            }
        } else
            // If there is only one element in the $parts array, replace all newline characters in $parts[0] with spaces and store the result in $fileContent.
            $fileContent = str_replace( "\n", " ", $parts[0] );

        // remove optional ending tags {@link http://www.w3.org/TR/html5/syntax.html#syntax-tag-omission}
        $remove = [ '</option>', '</li>', '</dt>', '</dd>', '</tr>', '</th>', '</td>', ];
        $fileContent = trim( str_ireplace( $remove, '', $fileContent ) );

        if ( DEV_MODE === true || ca()->has( $cacheKey ) === false ) {
            $result = [
                'className' => $newClassName,
                'fileContent' => substr( $fileContent, 5 ),
            ];

            ca()->set( $cacheKey, j_encode( $result ) );
        }

        return j_decode( ca()->get( $cacheKey ), true );
    }

    /**
     * Get templates definition
     *
     * @return string[]
     */
    public function getTemplatesDefinition(): array
    {
        return self::$templatesDefinition;
    }

    /**
     * Remove PHP comments from file
     *
     * @param string $filename
     *
     * @return string
     */
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
