<?php /** @noinspection GlobalVariableUsageInspection */
declare( strict_types=1 );

namespace PHP_SF\System;

use JetBrains\PhpStorm\NoReturn;
use JetBrains\PhpStorm\Pure;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Classes\Exception\InvalidRouteMethodParameterTypeException;
use PHP_SF\System\Classes\Exception\RouteParameterException;
use PHP_SF\System\Classes\Exception\ViewException;
use PHP_SF\System\Core\MiddlewareEventDispatcher;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Database\DoctrineEntityManager;
use PHP_SF\System\Interface\MiddlewareInterface;
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

    private function __construct()
    {
    }

    public static function init( Kernel|null $kernel = null ): void
    {
        if ( $kernel !== null )
            self::$kernel = $kernel;

        elseif ( isset( self::$kernel ) === false )
            throw new InvalidConfigurationException( 'Kernel must be set before calling Router::init() without passing it as a parameter!' );

        DoctrineEntityManager::invalidateEntityManager( self::$kernel );

        static::parseRoutes();

        $currentRoute = static::setCurrentRoute();
        if ( $currentRoute === true )
            static::route();

    }

    protected static function parseRoutes(): void
    {
        if ( empty( static::$routesList ) ) {
            if ( ( $routesList = ra()->get( 'cache:routes_list' ) ) === null ) {
                foreach ( static::getControllersDirectories() as $controllersDirectory )
                    static::controllersFromDir( $controllersDirectory );

                if ( DEV_MODE === false )
                    ra()->set( 'cache:routes_list', j_encode( static::$routesList ), null );

            } else
                static::$routesList = j_decode( $routesList, true );


            if ( empty( static::$routesByUrl ) ) {
                if ( ( $routesByUrl = ra()->get( 'cache:routes_by_url_list' ) ) === null ) {
                    foreach ( static::$routesList as $route )
                        static::$routesByUrl[ $route['httpMethod'] ][ $route['url'] ] = $route;

                    if ( DEV_MODE === false )
                        ra()->set( 'cache:routes_by_url_list', j_encode( static::$routesByUrl ), null );

                } else
                    static::$routesByUrl = j_decode( $routesByUrl, true );

            }
        }
    }

    protected static function getControllersDirectories(): array
    {
        return static::$controllersDirectories;
    }

    protected static function controllersFromDir( string $dir ): void
    {
        $filesAndDirectories = array_diff( scandir( $dir ), [ '.', '..' ] );

        foreach ( $filesAndDirectories as $fileOrDirectory ) {
            $path = "$dir/$fileOrDirectory";

            if ( is_dir( $path ) )
                static::controllersFromDir( $path );

            else {
                $array1 = explode( '/', $path );
                $fileName = str_replace( '.php', '', ( end( $array1 ) ) );
                $array2 = explode( '../', "$dir/$fileName" );

                if ( is_dir( sprintf( '../%s', end( $array2 ) ) ) )
                    static::controllersFromDir( end( $array2 ) );

                else {
                    $var = explode( 'namespace ', file_get_contents( $path ) );
                    $namespace = explode( ';', $var[1], 2 )[0];
                    static::routesFromController( $namespace, $fileName );
                }
            }
        }
    }

    protected static function routesFromController( string $namespace, string $fileName ): void
    {
        $reflectionClass = new ReflectionClass( "$namespace\\$fileName" );

        foreach ( $reflectionClass->getMethods() as $reflectionMethod ) {
            $routeAttributes = $reflectionMethod->getAttributes( Route::class );

            if ( !empty( $routeAttributes ) ) {
                $attribute = end( $routeAttributes );
                $arguments = $attribute->getArguments();

                $url = $arguments['url'] ?? $arguments[0];

                if ( $url !== '/' ) {
                    if ( $url[0] !== '/' )
                        $url = "/$url";

                    if ( $url[ -1 ] === '/' )
                        $url = substr( $url, 0, -1 );

                }

                static::setRoute(
                    (object)[
                        'url' => $url,
                        'httpMethod' => $arguments['httpMethod'],
                        'class' => $reflectionClass->getName(),
                        'name' => $arguments['name'] ?? $reflectionMethod->getName(),
                        'method' => $reflectionMethod->getName(),
                        'middleware' => $arguments['middleware'] ?? null,
                    ]
                );
            }
        }
    }

    protected static function setRoute( object $data ): void
    {
        $arr1 = ( explode( '{$', $data->url ) );
        unset( $arr1[0] );

        $arr2 = [];
        $routeParams = [];
        foreach ( $arr1 as $str )
            $arr2[] = explode( '/', $str )[0];
        foreach ( $arr2 as $str )
            $routeParams[] = explode( '}', $str )[0];

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

    protected static function checkParams( object $data ): void
    {
        if ( $data->middleware !== null ) {
            if ( is_countable( $data->middleware ) === false )
                $data->middleware = [ $data->middleware ];

            foreach ( $data->middleware as $middleware ) {
                $middlewareReflectionClass = new ReflectionClass( $middleware );

                if ( $middlewareReflectionClass->implementsInterface( MiddlewareInterface::class ) === false )
                    throw new RuntimeException(
                        "Middleware for route `$data->name` in class `$data->class` must implements " .
                        MiddlewareInterface::class
                    );

            }
        }

        self::checkMethodParameterTypes( $data );

        if ( array_key_exists( $data->httpMethod, static::ALLOWED_HTTP_METHODS ) === false )
            throw new InvalidConfigurationException(
                "Undefined HTTP method `$data->httpMethod` for route `$data->name` in class `$data->class`",
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

        $currentUrl = static::getCurrentRequestUrl();
        $urlHash = hash( 'sha256', $currentUrl );

        if ( ra()->get( 'parsed_url:' . $urlHash ) ) {
            static::$currentRoute = j_decode( ra()->get( 'parsed_url:route:' . $urlHash ) );
            static::$routeParams = j_decode( ra()->get( 'parsed_url:route_params:' . $urlHash ), true );

            return true;
        }

        # Array looks like this: [ '', 'controller', 'method', 'param1', 'param2', ... ]
        $currentUrlArray = explode( '/', $currentUrl );

        # Delete first empty element
        array_shift( $currentUrlArray );

        # Delete last element if it's empty
        if ( end( $currentUrlArray ) === '' )
            array_pop( $currentUrlArray );


        /**
         * Values from {@see Router::ALLOWED_HTTP_METHODS} constant }
         */
        $httpMethod = $_SERVER['REQUEST_METHOD'];

        if ( array_key_exists( $httpMethod, self::$routesByUrl ) === false )
            throw new RuntimeException( 'Looks like something went wrong with parsing routes from controllers.' .
                ' Routes list for current httpMethod is empty. Please, check your controllers and routes!' );

        # Looking for a route with the same url (without parameters)
        foreach ( static::$routesByUrl[ $httpMethod ] as $routeUrl => $route ) {
            if ( $currentUrl === $routeUrl ) {
                static::$currentRoute = (object)static::$routesByUrl[ $httpMethod ][ $currentUrl ];

                ra()->setMultiple( [
                    'parsed_url:' . $urlHash => $currentUrl,
                    'parsed_url:route:' . $urlHash => j_encode( static::$currentRoute ),
                    'parsed_url:route_params:' . $urlHash => j_encode( [] )
                ] );

                return true;
            }
        }

        $arr = static::$routesByUrl[ $httpMethod ];

        # Loop through all routes to remove routes with different number of parameters
        foreach ( $arr as $possibleRouteUrl => $possibleRoute ) {
            # Array looks like this: [ '', 'controller', 'method', 'param1', 'param2', ... ]
            $routeUrlArray = explode( '/', $possibleRouteUrl );
            # Delete first empty element
            array_shift( $routeUrlArray );

            if ( count( $currentUrlArray ) !== count( $routeUrlArray ) )
                unset( $arr[ $possibleRouteUrl ] );

        }

        # Loop through all routes to remove routes with different parameters name
        foreach ( $arr as $possibleRouteUrl => $possibleRoute ) {
            $routeUrlArray = explode( '/', $possibleRouteUrl );
            array_shift( $routeUrlArray );

            foreach ( $routeUrlArray as $key => $value )
                if ( str_starts_with( $value, '{$' ) === false && $value !== $currentUrlArray[ $key ] )
                    unset( $arr[ $possibleRouteUrl ] );

        }

        $possibleRoutes = [];
        # Loop through all routes to create new array with possible routes with url as a key
        foreach ( $arr as $possibleRouteUrl => $possibleRoute ) {
            $routeUrlArray = explode( '/', $possibleRouteUrl );
            array_shift( $routeUrlArray ); # Delete first empty element

            $possibleRoutes[ $possibleRouteUrl ] = 0;
            foreach ( $routeUrlArray as $value )
                if ( str_starts_with( $value, '{$' ) )
                    $possibleRoutes[ $possibleRouteUrl ]++;

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
            # Sort and save route
            arsort( $possibleRoutes, SORT_DESC );
            static::$currentRoute = (object)$arr[ array_key_last( $possibleRoutes ) ];

            $routeUrlArray = explode( '/', static::$currentRoute->url );
            array_shift( $routeUrlArray );

            # Save parameters
            foreach ( $routeUrlArray as $key => $str )
                if ( str_starts_with( $str, '{$' ) )
                    static::$routeParams[ str_replace( [ '{$', '}' ], '', $str ) ] = $currentUrlArray[ $key ];

            ra()->setMultiple( [
                'parsed_url:' . $urlHash => $currentUrl,
                'parsed_url:route:' . $urlHash => j_encode( static::$currentRoute ),
                'parsed_url:route_params:' . $urlHash => j_encode( static::$routeParams )
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
    private static function route(): void
    {
        static::setRequest();
        static::setRequestHeaders();
        static::setHttpMethod( static::$currentRoute->httpMethod );

        static::initializeController();

        static::setRouteParameters();

        static::initializeRouteMiddlewares();

        static::initializeRouteMethod();

        static::sendRouteMethodResponse();
    }

    protected static function setRequest(): void
    {
        static::$requestData = new Request(
            $_GET,
            array_merge(
                $_POST,
                ( json_decode( file_get_contents( 'php://input' ), true ) ?? [] )
            )
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

    protected static function getRequest(): Request
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

    protected static function initializeRouteMiddlewares(): void
    {
        $routeMiddleware = static::$currentRoute->middleware;

        if ( $routeMiddleware !== null ) {
            if ( !is_array( $routeMiddleware ) )
                $routeMiddleware = [ $routeMiddleware ];

            foreach ( $routeMiddleware as $middleware ) {
                $middlewareInstance = new $middleware( static::getRequest(), self::$kernel );

                new MiddlewareEventDispatcher(
                    $middlewareInstance,
                    static::getRequest(),
                    self::$controller
                );
            }

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
                static function ( $b ) {
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
