<?php /** @noinspection GlobalVariableUsageInspection */
declare( strict_types=1 );

namespace PHP_SF\System;

use JetBrains\PhpStorm\NoReturn;
use JetBrains\PhpStorm\Pure;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Classes\Abstracts\Middleware;
use PHP_SF\System\Classes\Exception\InvalidRouteMethodParameterTypeException;
use PHP_SF\System\Classes\Exception\RouteParameterException;
use PHP_SF\System\Classes\Exception\ViewException;
use PHP_SF\System\Classes\MiddlewareChecks\MiddlewaresExecutor;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Database\DoctrineEntityManager;
use PHP_SF\System\Traits\RedirectTrait;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionUnionType;
use RuntimeException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Throwable;
use function apache_request_headers;
use function array_key_exists;
use function count;
use function function_exists;
use function is_array;

class Router
{
    use RedirectTrait;


    private static AbstractController $controller;
    private static JsonResponse|Response|RedirectResponse $routeMethodResponse;

    private const ALLOWED_HTTP_METHODS = [
        'GET' => '', 'POST' => '', 'PUT' => '', 'PATCH' => '', 'DELETE' => '',
    ];

    public static object|null $currentRoute = null;
    private static array $routeParams = [];
    private static Request $requestData;
    /**
     * @var array<object>
     */
    private static array $routesList = [];
    /**
     * @var array<object>
     */
    private static array $routesByUrl = [];
    private static string $currentHttpMethod;
    private static array $controllersDirectories = [];

    private static Kernel $kernel;

    private function __construct() {}

    public static function init( Kernel $kernel = null ): void
    {
        self::$kernel = $kernel
            ?? self::$kernel
            ?? throw new InvalidConfigurationException(
                'Kernel must be set before calling Router::init() without passing it as a parameter!'
            );

        DoctrineEntityManager::invalidateEntityManager();

        static::parseRoutes();

        if ( static::setCurrentRoute() )
            static::route();

    }

    protected static function parseRoutes(): void
    {
        if ( empty( static::$routesList ) === false )
            return;

        $routesList = ca()->get( 'cache:routes_list' );
        if ( $routesList === null ) {
            foreach ( static::getControllersDirectories() as $controllersDirectory )
                static::controllersFromDir( $controllersDirectory );

            if ( DEV_MODE === false )
                ca()->set( 'cache:routes_list', j_encode( static::$routesList ), null );

        } else
            static::$routesList = j_decode( $routesList, true );

        if ( empty( static::$routesByUrl ) === false )
            return;

        $routesByUrl = ca()->get( 'cache:routes_by_url_list' );
        if ( $routesByUrl !== null ) {
            static::$routesByUrl = j_decode( $routesByUrl, true );

            return;
        }

        foreach ( static::$routesList as $route )
            static::$routesByUrl[ $route['httpMethod'] ][ $route['url'] ] = $route;

        if ( DEV_MODE === false )
            ca()->set( 'cache:routes_by_url_list', j_encode( static::$routesByUrl ), null );

    }

    protected static function getControllersDirectories(): array
    {
        return static::$controllersDirectories;
    }

    protected static function controllersFromDir( string $dir ): void
    {
        $files = array_filter( scandir( $dir ), function ( $file ) {
            return in_array( $file, [ '.', '..' ] ) === false;
        } );

        foreach ( $files as $file ) {
            $path = "$dir/$file";

            if ( is_dir( $path ) ) {
                static::controllersFromDir( $path );
                continue;
            }

            if ( preg_match( '/\.php$/', $file ) === false )
                continue;

            $fileName  = str_replace( '.php', '', $file );
            $namespace = self::extractNamespace( $path );

            if ( $namespace === null )
                continue;

            static::routesFromController( $namespace, $fileName );
        }
    }

    protected static function extractNamespace( string $path ): string|null
    {
        $contents = file_get_contents( $path );
        if ( preg_match( '/namespace (.+);/', $contents, $matches ) )
            return $matches[1];

        return null;
    }

    protected static function routesFromController( string $namespace, string $fileName ): void
    {
        $reflectionClass = new ReflectionClass( "$namespace\\$fileName" );
        $routeMethods    = [];

        foreach ( $reflectionClass->getMethods( ReflectionMethod::IS_PUBLIC ) as $reflectionMethod ) {
            $routeAttributes = $reflectionMethod->getAttributes( Route::class );

            if ( !empty( $routeAttributes ) )
                $routeMethods[] = $reflectionMethod;

        }

        foreach ( $routeMethods as $routeMethod ) {
            $routeAttributes = $routeMethod->getAttributes( Route::class );
            $attribute       = end( $routeAttributes );
            $arguments       = $attribute->getArguments();

            $url = $arguments['url'] ?? $arguments[0];

            if ( $url !== '/' ) {
                $url = rtrim( $url, '/' );
                if ( $url[0] !== '/' )
                    $url = "/$url";

            }

            static::setRoute(
                (object)[
                    'url'        => $url,
                    'httpMethod' => $arguments['httpMethod'],
                    'class'      => $reflectionClass->getName(),
                    'name'       => $arguments['name'] ?? $routeMethod->getName(),
                    'method'     => $routeMethod->getName(),
                    'middleware' => $arguments['middleware'] ?? null,
                ]
            );
        }
    }

    protected static function setRoute( object $data ): void
    {
        preg_match_all( '/\{\$(.*?)}/', $data->url, $matches );
        $routeParams = [];
        foreach ( $matches[1] as $match )
            $routeParams[] = $match;

        $data = [
            'url' => $data->url,
            'class' => $data->class,
            'method' => $data->method,
            'name' => $data->name,
            'httpMethod' => $data->httpMethod,
            'middleware' => $data->middleware,
            'routeParams' => $routeParams,
        ];

        self::checkParams( (object)$data );

        static::$routesList[ $data['name'] ] = $data;
    }

    /**
     * Check if the provided data object contains valid parameters for route definition
     *
     * @param object $data The object containing data for route definition
     *
     * @throws RouteParameterException If the provided data object contains invalid parameters for route definition
     * @throws ReflectionException
     */
    protected static function checkParams( object $data ): void
    {
        // Check if middlewares extends Middleware class
        if ( $data->middleware ) {
            // If middleware is an array or not, make it an array for easy iteration
            $middleware = is_array( $data->middleware ) ? $data->middleware : [ $data->middleware ];

            foreach ( $middleware as $m ) {

                if ( is_array( $m ) ) {
                    // If middleware is nested array, check each of them
                    foreach ( $m as $mm ) {

                        if ( is_array( $mm ) ) {
                            // If middleware is another nested array, check each of them
                            foreach ( $mm as $mmm )
                                // Check if middleware extends Middleware class, if not throw an exception
                                if ( ( new ReflectionClass( $mmm ) )->getParentClass()->getName() !== Middleware::class )
                                    throw new RuntimeException(
                                        "Middleware for route $data->name in class $data->class must be extended from " . Middleware::class
                                    );

                        } elseif ( ( new ReflectionClass( $mm ) )->getParentClass()->getName() !== Middleware::class )
                            // Check if middleware extends Middleware class, if not throw an exception
                            throw new RuntimeException(
                                "Middleware for route $data->name in class $data->class must be extended from " . Middleware::class
                            );

                    }

                } elseif ( ( new ReflectionClass( $m ) )->getParentClass()->getName() !== Middleware::class )
                    // Check if middleware extends Middleware class, if not throw an exception
                    throw new RuntimeException(
                        "Middleware for route $data->name in class $data->class must be extended from " . Middleware::class
                    );

            }
        }

        // Check if method parameters are of allowed types
        self::checkMethodParameterTypes( $data );

        // Check if HTTP method is allowed
        if ( array_key_exists( $data->httpMethod, static::ALLOWED_HTTP_METHODS ) === false )
            throw new InvalidConfigurationException(
                "Undefined HTTP method `$data->httpMethod` for route `$data->name` in class `$data->class`"
            );

    }

    private static function checkMethodParameterTypes( object $data ): void
    {
        $reflectionMethod = new ReflectionMethod( $data->class, $data->method );

        foreach ( $reflectionMethod->getParameters() as $reflectionParameter ) {
            if ( $reflectionParameter->getType() instanceof ReflectionUnionType )
                throw new RouteParameterException(
                    sprintf( 'Method parameter "%s" in the %s::%s route cannot be a union type!',
                        $reflectionParameter->getName(), $data->class, $reflectionMethod->getName()
                    )
                );

            self::checkMethodParameterType(
                $reflectionParameter->getType()?->getName(),
                $reflectionParameter->getName(),
                $data
            );
        }
    }

    private static function checkMethodParameterType( string $type, string $propertyName, object $data ): void
    {
        switch ( $type ) {
            case 'int':
            case 'float':
            case 'string':
                break;
            default:
                throw new InvalidRouteMethodParameterTypeException( $type, $propertyName, $data );
        }
    }

    protected static function setCurrentRoute(): bool
    {
        # We need to clear the current route, because it can be set before redirecting to another route
        static::$currentRoute = null;
        static::$routeParams = [];

        /**
         * Values from {@see Router::ALLOWED_HTTP_METHODS} constant }
         */
        $httpMethod = $_SERVER['REQUEST_METHOD'];

        if ( array_key_exists( $httpMethod, self::$routesByUrl ) === false )
            throw new RuntimeException( 'Looks like something went wrong with parsing routes from controllers.' .
                                        ' Routes list for current httpMethod is empty. Please, check your controllers and routes!' );

        $currentUrl = static::getCurrentRequestUrl();
        $urlHash = hash( 'sha256', $currentUrl );

        if ( ca()->get( sprintf( "parsed_url:%s:%s", $httpMethod, $urlHash ) ) ) {
            static::$currentRoute = j_decode( ca()->get( sprintf( "parsed_url:%s:route:%s", $httpMethod, $urlHash ) ) );
            static::$routeParams = j_decode( ca()->get( sprintf( "parsed_url:%s:route_params:%s", $httpMethod, $urlHash ) ), true );

            return true;
        }

        # Array looks like this: [ '', 'controller', 'method', 'param1', 'param2', ... ]
        $currentUrlArray = explode( '/', $currentUrl );

        # Delete first empty element
        array_shift( $currentUrlArray );

        # Delete last element if it's empty
        if ( end( $currentUrlArray ) === '' )
            array_pop( $currentUrlArray );

        # Looking for a route with the same url (without parameters)
        foreach ( static::$routesByUrl[ $httpMethod ] as $routeUrl => $route ) {
            if ( $currentUrl === $routeUrl ) {
                static::$currentRoute = (object)static::$routesByUrl[ $httpMethod ][ $currentUrl ];

                ca()->setMultiple( [
                    sprintf( "parsed_url:%s:%s", $httpMethod, $urlHash ) => $currentUrl,
                    sprintf( "parsed_url:%s:route:%s", $httpMethod, $urlHash ) => j_encode( static::$currentRoute ),
                    sprintf( "parsed_url:%s:route_params:%s", $httpMethod, $urlHash ) => j_encode( [] )
                ] );

                return true;
            }
        }

        $arr = static::$routesByUrl[ $httpMethod ];
        $possibleRoutes = [];

        # Looking for a route with the same url (with parameters)
        foreach ( $arr as $possibleRouteUrl => $possibleRoute ) {
            # Splits the possible route URL into an array of individual components using "/" as a delimiter
            $routeUrlArray = explode( '/', $possibleRouteUrl );
            # Removes the first item from the array as it will always be an empty string
            array_shift( $routeUrlArray );

            # Check if the length of the current URL and the possible route URL match
            if ( count( $currentUrlArray ) === count( $routeUrlArray ) ) {
                $match = true;
                # Check if each component in the possible route URL matches the corresponding component in the current URL
                foreach ( $routeUrlArray as $key => $value ) {
                    if ( str_starts_with( $value, '{$' ) === false && $value !== $currentUrlArray[ $key ] ) {
                        $match = false;
                        # If a mismatch is found, break out of the loop
                        break;
                    }
                }

                # If a match is found, store the possible route URL in an array with a count of the number of dynamic components in the URL
                if ( $match ) {
                    $possibleRoutes[ $possibleRouteUrl ] = 0;
                    foreach ( $routeUrlArray as $value )
                        if ( str_starts_with( $value, '{$' ) )
                            $possibleRoutes[ $possibleRouteUrl ]++;

                }
            }
        }

        /**
         * Sort all possible routes by number of parameters and save route with the least number of parameters
         *
         * So, if we have two routes: <b>/product/edit/{$id}</b> and <b>/product/{$category}/{$id}</b>,
         * router will select <b>/product/edit/{$id}</b>
         *
         * Save route parameters to {@see static::$routeParams}
         */
        if ( empty( $possibleRoutes ) === false ) {
            # Sort and save route with the least number of parameters
            arsort( $possibleRoutes, SORT_DESC );
            static::$currentRoute = (object)$arr[ array_key_last( $possibleRoutes ) ];

            # Split the URL of the selected route into an array of components
            $routeUrlArray = explode( '/', static::$currentRoute->url );
            array_shift( $routeUrlArray );

            # Save the parameters of the route
            foreach ( $routeUrlArray as $key => $str )
                if ( str_starts_with( $str, '{$' ) )
                    static::$routeParams[ str_replace( [ '{$', '}' ], '', $str ) ] = $currentUrlArray[ $key ];

            # Store the selected URL, route and its parameters in a cache
            ca()->setMultiple( [
                sprintf( "parsed_url:%s:%s", $httpMethod, $urlHash ) => $currentUrl,
                sprintf( "parsed_url:%s:route:%s", $httpMethod, $urlHash ) => j_encode( static::$currentRoute ),
                sprintf( "parsed_url:%s:route_params:%s", $httpMethod, $urlHash ) => j_encode( static::$routeParams )
            ] );
        }

        return static::$currentRoute !== null;
    }

    protected static function getCurrentRequestUrl(): string
    {
        $currentUrl = explode( '?', $_SERVER['REQUEST_URI'] )[0];
        if ( empty( $currentUrl ) )
            $currentUrl = '/';

        return $currentUrl;
    }

    #[NoReturn]
    private static function route(): never
    {
        static::setRequest();
        static::setRequestHeaders();
        static::setHttpMethod( static::$currentRoute->httpMethod );

        static::initializeController();

        static::setRouteParameters();

        static::initializeRouteMiddlewares();

        static::initializeRouteMethod();

        static::sendRouteMethodResponse();

        exit(die());
    }

    protected static function setRequest(): void
    {
        static::$requestData = new Request(
            $_GET, array_merge( $_POST, ( json_decode( file_get_contents( 'php://input' ), true ) ?? [] ) )
        );
    }

    protected static function setRequestHeaders(): void
    {
        if ( function_exists( 'apache_request_headers' ) )
            foreach ( apache_request_headers() as $headerName => $value )
                static::$requestData->headers->set( $headerName, $value );

    }

    private static function setHttpMethod( string $method ): void
    {
        static::$requestData->setMethod( $method );
    }

    protected static function initializeController(): void
    {
        self::$controller = new ( static::$currentRoute->class )( static::getRequest() );
    }

    final protected static function getRequest(): Request
    {
        return static::$requestData;
    }

    protected static function setRouteParameters(): void
    {
        if ( !empty( static::$routeParams ) ) {
            $reflectionMethod = new ReflectionMethod( static::$currentRoute->class, static::$currentRoute->method );

            foreach ( $reflectionMethod->getParameters() as $reflectionParameter ) {
                if ( array_key_exists( $reflectionParameter->getName(), static::$routeParams ) === false )
                    throw new RouteParameterException(
                        sprintf( 'Method parameter "%s" in the %s::%s route does not match the variables from route URL!',
                            $reflectionParameter->getName(), static::$currentRoute->class, $reflectionMethod->getName()
                        )
                    );

                $parameterValue = static::$routeParams[ $reflectionParameter->getName() ];

                settype( $parameterValue, $reflectionParameter->getType()?->getName() );

                static::$routeParams[ $reflectionParameter->getName() ] = $parameterValue;

            }
        }
    }

    private static function initializeRouteMiddlewares(): void
    {
        $me = new MiddlewaresExecutor( static::$currentRoute->middleware ?? [],
            self::getRequest(), self::$kernel, self::$controller
        );

        $mResult = $me->execute();

        if ( $mResult !== true ) {
            $mResult->send();

            exit( die );
        }
    }

    /**
     * @throws ReflectionException
     * @throws RouteParameterException
     */
    private static function initializeRouteMethod(): void
    {
        $reflectionMethod = new ReflectionMethod( static::$currentRoute->class, static::$currentRoute->method );
        $methodParameters = $reflectionMethod->getParameters();
        $methodParametersCount = count( $methodParameters );
        if ( $methodParametersCount === 0 ) {
            if ( count( static::$routeParams ) !== 0 )
                throw new RouteParameterException(
                    sprintf( 'Method parameters count in the %s::%s route do not match the variables count from route URL!',
                        static::$currentRoute->class, $reflectionMethod->getName()
                    )
                );

            static::$routeMethodResponse = $reflectionMethod->invoke( self::$controller );

            return;
        }

        $methodParametersValues = [];
        foreach ( $methodParameters as $reflectionParameter ) {
            if ( array_key_exists( $reflectionParameter->getName(), static::$routeParams ) === false )
                throw new RouteParameterException(
                    sprintf( 'Route url does not contain the "%s" parameter in the %s::%s route!',
                        $reflectionParameter->getName(), static::$currentRoute->class, $reflectionMethod->getName()
                    )
                );

            $methodParametersValues[] = static::$routeParams[ $reflectionParameter->getName() ];
        }

        if ( $methodParametersCount !== count( $methodParametersValues ) || $methodParametersCount !== count( static::$routeParams ) )
            throw new RouteParameterException(
                sprintf( 'Method parameters count in the %s::%s route do not match the variables count from route URL!',
                    static::$currentRoute->class, $reflectionMethod->getName()
                )
            );


        try {
            static::$routeMethodResponse = $reflectionMethod->invokeArgs( self::$controller, $methodParametersValues );
        } catch ( ReflectionException $e ) {
            throw new RouteParameterException(
                sprintf( 'Method parameters in the %s::%s route do not match the variables from route URL!',
                    static::$currentRoute->class, $reflectionMethod->getName()
                ), $e->getCode(), $e
            );
        }
    }

    #[NoReturn]
    protected static function sendRouteMethodResponse(): void
    {
        if ( self::$routeMethodResponse instanceof Response ) {
            ob_start(
                function ( $b ) {
                    if ( TEMPLATES_CACHE_ENABLED )
                        return preg_replace( [ '/>\s+</' ], [ '><' ], $b );

                    return $b;
                }
            );

            try {
                static::$routeMethodResponse->send();
            } catch ( Throwable $e ) {
                ob_end_clean();
                throw new ViewException( $e->getMessage(), $e->getCode(), E_ERROR, $e->getFile(), $e->getLine(), $e );
            }
        }

        self::$routeMethodResponse->send();
    }

    #[Pure]
    public static function getRouteLink( string $routeName ): string
    {
        return self::isRouteExists( $routeName ) === false ?
            "#$routeName" : static::$routesList[ $routeName ]['url'];
    }

    final public static function isRouteExists( string $routeName ): bool
    {
        return array_key_exists( $routeName, self::$routesList );
    }

    public static function getRouteInfo( string $routeName ): array
    {
        if ( self::isRouteExists( $routeName ) === false )
            throw new RouteNotFoundException();

        return self::$routesList[ $routeName ];
    }

    public static function addControllersDirectory( string $controllersDirectory ): void
    {
        static::$controllersDirectories[] = $controllersDirectory;
    }

    public static function getRoutesList(): array
    {
        return static::$routesList;
    }

    private function __clone()
    {
    }

}
