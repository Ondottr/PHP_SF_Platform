<?php /** @noinspection GlobalVariableUsageInspection */
declare( strict_types=1 );

namespace PHP_SF\System;

use ErrorException;
use JetBrains\PhpStorm\NoReturn;
use JetBrains\PhpStorm\Pure;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Classes\Exception\InvalidRouteMethodParameterTypeException;
use PHP_SF\System\Core\MiddlewareEventDispatcher;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Interface\MiddlewareInterface;
use PHP_SF\System\Traits\RedirectTrait;
use ReflectionClass;
use ReflectionMethod;
use ReflectionUnionType;
use RuntimeException;
use Symfony\Component\ErrorHandler\Error\UndefinedMethodError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

use function apache_request_headers;
use function array_key_exists;
use function count;
use function function_exists;
use function is_array;


class Router
{

    use RedirectTrait;


    protected const ALLOWED_HTTP_METHODS = [
        'GET' => '', 'POST' => '', 'PUT' => '', 'PATCH' => '', 'DELETE' => '',
    ];

    public static array                                     $links       = [];
    public static object                                    $currentRoute;
    protected static array                                  $routeParams = [];
    protected static Request                                $requestData;
    protected static JsonResponse|Response|RedirectResponse $routeMethodResponse;
    /**
     * @var array|object[]
     */
    private static array $routesList = [];
    /**
     * @var array|object[]
     */
    private static array              $routesByUrl            = [];
    private static string             $currentHttpMethod;
    private static array              $controllersDirectories = [];
    private static AbstractController $controller;


    private function __construct() {}

    public static function init(): void
    {
        static::parseRoutes();

        if ( static::setCurrentRoute() === true )
            static::route();

    }

    protected static function parseRoutes(): void
    {
        if ( empty( static::$routesList ) ) {
            if ( ( $routesList = rc()->get( 'cache:routes_list' ) ) === null ) {
                foreach ( static::getControllersDirectories() as $controllersDirectory ) {
                    static::controllersFromDir( $controllersDirectory );
                }

                if ( DEV_MODE === false ) {
                    rp()->set(
                        'cache:routes_list',
                        j_encode( static::$routesList )
                    );
                }
            }
            else {
                static::$routesList = j_decode( $routesList, true );
            }

            if ( empty( static::$routesByUrl ) ) {
                if ( ( $routesByUrl = rc()->get( 'cache:routes_by_url_list' ) ) === null ) {
                    foreach ( static::$routesList as $route ) {
                        static::$routesByUrl[ $route['httpMethod'] ][ $route['url'] ] = $route;
                    }

                    if ( DEV_MODE === false ) {
                        rp()->set(
                            'cache:routes_by_url_list',
                            j_encode( static::$routesByUrl )
                        );
                    }
                }
                else {
                    static::$routesByUrl = j_decode( $routesByUrl, true );
                }
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

            if ( is_dir( $path ) ) {
                static::controllersFromDir( $path );
            }
            else {
                $array1   = explode( '/', $path );
                $fileName = str_replace( '.php', '', ( end( $array1 ) ) );
                $array2   = explode( '../', "$dir/$fileName" );

                if ( is_dir( sprintf( '../%s', end( $array2 ) ) ) ) {
                    static::controllersFromDir( end( $array2 ) );
                }
                else {
                    $var       = explode( 'namespace ', file_get_contents( $path ) );
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
                        'url'        => $url,
                        'httpMethod' => $arguments['httpMethod'],
                        'class'      => $reflectionClass->getName(),
                        'name'       => $arguments['name'] ?? $reflectionMethod->getName(),
                        'method'     => $reflectionMethod->getName(),
                        'middleware' => $arguments['middleware'] ?? null,
                    ]
                );
            }
        }
    }

    protected static function setRoute( object $data ): void
    {
        try {
            static::checkParams( $data );
        } catch ( ConflictHttpException ) {

        }


        $arr1 = ( explode( '{$', $data->url ) );
        unset( $arr1[0] );

        $arr2        = [];
        $routeParams = [];
        foreach ( $arr1 as $str )
            $arr2[] = explode( '/', $str )[0];
        foreach ( $arr2 as $str )
            $routeParams[] = explode( '}', $str )[0];

        static::$routesList[ $data->name ] = [
            'url'         => $data->url,
            'class'       => $data->class,
            'method'      => $data->method,
            'name'        => $data->name,
            'httpMethod'  => $data->httpMethod,
            'middleware'  => $data->middleware,
            'routeParams' => $routeParams,
        ];

        static::$links[ $data->httpMethod ][ $data->url ] = '';
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

        if ( !isset( static::ALLOWED_HTTP_METHODS[ $data->httpMethod ] ) )
            throw new UndefinedMethodError(
                "Undefined HTTP method `$data->httpMethod` for route `$data->name` in class `$data->class`",
                new ErrorException()
            );

        if ( !array_key_exists( $data->name, static::$routesList ) &&
             array_key_exists( $data->httpMethod, static::$links ) &&
             array_key_exists( $data->url, static::$links[ $data->httpMethod ] )
        )
            throw new ConflictHttpException( "Route for url `$data->url` already exists!" );

    }

    private static function checkMethodParameterTypes( object $data ): void
    {
        $reflectionMethod = new ReflectionMethod( $data->class, $data->method );

        foreach ( $reflectionMethod->getParameters() as $reflectionNameType ) {
            if ( ( $reflectionsUnionType = $reflectionNameType->getType() ) instanceof ReflectionUnionType ) {
                /**
                 * @noinspection PhpForeachNestedOuterKeyValueVariablesConflictInspection
                 * @noinspection SuspiciousLoopInspection
                 */
                foreach ( $reflectionsUnionType as $reflectionNameType ) {
                    self::checkMethodParameterType(
                        $reflectionNameType->getName(),
                        $reflectionsUnionType->getName(),
                        $data
                    );
                }
            }
            else
                self::checkMethodParameterType(
                    $reflectionNameType->getType()->getName(),
                    $reflectionNameType->getName(),
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
        static::$routeParams = [];

        $currentUrl      = static::getCurrentRequestUrl();
        $currentUrlArray = explode( '/', $currentUrl );

        $httpMethod = $_SERVER['REQUEST_METHOD'];

        if ( !isset( static::$routesByUrl[ $httpMethod ] ) )
            return false;

        foreach ( static::$routesByUrl[ $httpMethod ] as $url => $route )
            if ( $currentUrl === $url ) {
                static::$currentRoute = (object)static::$routesByUrl[ $httpMethod ][ $currentUrl ];

                return true;
            }


        foreach ( static::$routesByUrl[ $httpMethod ] as $routeUrl => $route ) {
            $routeUrlArray = explode( '/', $routeUrl );

            if ( count( $currentUrlArray ) === count( $routeUrlArray ) ) {
                static::$routeParams = [];

                for ( $i = 0, $iMax = count( $currentUrlArray ); $i < $iMax; $i++ ) {
                    if ( str_starts_with( $routeUrlArray[ $i ], '{$' ) ) {
                        static::$currentRoute = (object)$route;

                        while ( $i < $iMax ) {
                            if ( str_starts_with( $routeUrlArray[ $i ], '{$' ) ) {
                                static::$routeParams[ str_replace(
                                    [ '{$', '}' ],
                                    '',
                                    $routeUrlArray[ $i ]
                                ) ] = $currentUrlArray[ $i ];
                            }
                            elseif ( $routeUrlArray[ $i ] !== $currentUrlArray[ $i ] ) {
                                goto continueLoop;
                            }

                            $i++;
                        }

                        return true;
                    }
                    elseif ( $routeUrlArray[ $i ] !== $currentUrlArray[ $i ] ) {
                        continueLoop:

                        break;
                    }
                }
            }
        }

        return false;
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
                if ( $reflectionParameter->getType() instanceof ReflectionUnionType === false ) {
                    $parameterValue = static::$routeParams[ $reflectionParameter->getName() ];

                    settype( $parameterValue, $reflectionParameter->getType()->getName() );

                    static::$routeParams[ $reflectionParameter->getName() ] = $parameterValue;
                }
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
                $middlewareInstance = new $middleware( static::getRequest() );

                new MiddlewareEventDispatcher(
                    $middlewareInstance,
                    static::getRequest(),
                    self::$controller
                );
            }

        }
    }

    private static function initializeRouteMethod(): void
    {
        static::$routeMethodResponse = ( self::$controller )->{static::$currentRoute->method}(
            ...static::$routeParams
        );
    }

    #[NoReturn]
    protected static function sendRouteMethodResponse(): never
    {
        static::$routeMethodResponse->send();

        die();
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

    private function __clone() {}

}
