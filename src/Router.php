<?php /** @noinspection GlobalVariableUsageInspection */
declare(strict_types=1);

namespace PHP_SF\System;

use JetBrains\PhpStorm\NoReturn;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHP_SF\System\Classes\Abstracts\Middleware;
use PHP_SF\System\Classes\Exception\InvalidRouteMethodParameterTypeException;
use PHP_SF\System\Classes\Exception\RouteParameterException;
use PHP_SF\System\Classes\Exception\ViewException;
use PHP_SF\System\Classes\MiddlewareChecks\MiddlewaresExecutor;
use PHP_SF\System\Core\ApiResponse;
use PHP_SF\System\Core\PhpSfContext;
use PHP_SF\System\Core\PhpSfEventDispatcher;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Core\TranslatorV2;
use PHP_SF\System\Traits\RedirectTrait;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use RuntimeException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\VarDumper\VarDumper;
use Throwable;

/**
 * @phpstan-type RouteData array{url: string, class: class-string, method: string, name: string, httpMethod: string, middleware: mixed, routeParams: list<string>}
 */
class Router
{
    use RedirectTrait;


    private const array ALLOWED_HTTP_METHODS = [
        'GET' => '', 'POST' => '', 'PUT' => '', 'PATCH' => '', 'DELETE' => '',
    ];

    public static ?object $currentRoute = null;

    /**
     * @var array<string> middleware classes run before every matched route's own middleware
     */
    protected static array $globalMiddlewares = [];

    private static AbstractController $controller;

    private static SymfonyResponse $routeMethodResponse;
    /**
     * @var array<string, mixed>
     */
    private static array $routeParams = [];

    private static Request $requestData;
    /**
     * @var array<string, RouteData>
     */
    private static array $routesList = [];
    /**
     * @var array<string, array<string, RouteData>>
     */
    private static array $routesByUrl = [];
    /**
     * @var list<string>
     */
    private static array $controllersDirectories = [];

    private static Kernel $kernel;


    private function __construct() {}


    private function __clone() {}


    /**
     * Registers one or more middleware classes to run globally on every matched route,
     * before that route's own middleware. Each middleware still controls its own skip
     * logic (e.g. skip for API routes, skip if a marker middleware is present).
     */
    public static function addGlobalMiddleware(string ...$classes): void
    {
        self::$globalMiddlewares = array_merge(self::$globalMiddlewares, $classes);
    }

    /**
     * Parses routes from all registered controller directories without dispatching any request.
     * Pass a Kernel to bind it for later use by {@see init()}; omit if only route parsing is needed
     * (controller directories must already be registered via {@see addControllersDirectory()}).
     */
    public static function loadRoutesOnly(?Kernel $kernel = null): void
    {
        if (null !== $kernel) {
            self::$kernel = $kernel;
        }

        static::parseRoutes();
    }

    public static function init(?Kernel $kernel = null): void
    {
        self::$kernel = $kernel
            ?? self::$kernel
            ?? throw new InvalidConfigurationException(
                'Kernel must be set before calling Router::init() without passing it as a parameter!',
            );

        $yamlConfigCacheKey = '/config/packages/doctrine.yaml';

        // parse yaml doctrine config
        $config = ca()->get($yamlConfigCacheKey);
        if (null === $config) {
            $config = yaml_parse_file(\App\Kernel::getInstance()->getProjectDir() . '/config/packages/doctrine.yaml');
            ca()->set($yamlConfigCacheKey, json_encode($config));
        } else {
            $config = json_decode($config, true);
        }

        // clear entity managers
        foreach ($config['doctrine']['orm']['entity_managers'] as $connection => $ignored) {
            em($connection)->clear();
        }

        TranslatorV2::getInstance()->loadCatalogs();

        static::parseRoutes();

        if (static::setCurrentRoute()) {
            try {
                self::route();
            } catch (Throwable $e) {
                $exceptionEvent = new ExceptionEvent(
                    self::$kernel,
                    self::$requestData,
                    HttpKernelInterface::MAIN_REQUEST,
                    $e,
                );
                PhpSfEventDispatcher::dispatch(KernelEvents::EXCEPTION, $exceptionEvent);

                if ($exceptionEvent->hasResponse()) {
                    $exResponse = $exceptionEvent->getResponse();
                    $exResponseEvent = new ResponseEvent(
                        self::$kernel,
                        self::$requestData,
                        HttpKernelInterface::MAIN_REQUEST,
                        $exResponse,
                    );
                    PhpSfEventDispatcher::dispatch(KernelEvents::RESPONSE, $exResponseEvent);
                    $exResponseEvent->getResponse()->send();
                    exit;
                }

                throw $e;
            }
        }
    }

    public static function getRouteLink(string $routeName): string
    {
        return false === self::isRouteExists($routeName) ?
            "#$routeName" : self::$routesList[$routeName]['url'];
    }

    final public static function isRouteExists(string $routeName): bool
    {
        return \array_key_exists($routeName, self::$routesList);
    }

    /**
     * @return RouteData
     */
    public static function getRouteInfo(string $routeName): array
    {
        if (false === self::isRouteExists($routeName)) {
            throw new RouteNotFoundException();
        }

        return self::$routesList[$routeName];
    }

    public static function addControllersDirectory(string $controllersDirectory): void
    {
        self::$controllersDirectories[] = $controllersDirectory;
    }

    /**
     * @return array<string, RouteData>
     */
    public static function getRoutesList(): array
    {
        return self::$routesList;
    }

    final public static function getRequest(): Request
    {
        return self::$requestData;
    }

    protected static function parseRoutes(): void
    {
        if (false === empty(self::$routesList)) {
            return;
        }

        $routesList = ca()->get('cache:routes_list');
        if (null === $routesList) {
            foreach (static::getControllersDirectories() as $controllersDirectory) {
                static::controllersFromDir($controllersDirectory);
            }

            if (DEV_MODE === false) {
                ca()->set('cache:routes_list', j_encode(self::$routesList), null);
            }
        } else {
            self::$routesList = j_decode($routesList, true);
        }

        if (false === empty(self::$routesByUrl)) {
            return;
        }

        $routesByUrl = ca()->get('cache:routes_by_url_list');
        if (null !== $routesByUrl) {
            self::$routesByUrl = j_decode($routesByUrl, true);

            return;
        }

        foreach (self::$routesList as $route) {
            self::$routesByUrl[$route['httpMethod']][$route['url']] = $route;
        }

        if (DEV_MODE === false) {
            ca()->set('cache:routes_by_url_list', j_encode(self::$routesByUrl), null);
        }
    }

    /**
     * @return list<string>
     */
    protected static function getControllersDirectories(): array
    {
        return self::$controllersDirectories;
    }

    protected static function controllersFromDir(string $dir): void
    {
        $files = array_filter(scandir($dir), function ($file) {
            return false === in_array($file, ['.', '..'], true);
        });

        foreach ($files as $file) {
            $path = "$dir/$file";

            if (is_dir($path)) {
                static::controllersFromDir($path);
                continue;
            }

            if (false === preg_match('/\.php$/', $file)) {
                continue;
            }

            $fileName = str_replace('.php', '', $file);
            $namespace = self::extractNamespace($path);

            if (null === $namespace) {
                continue;
            }

            static::routesFromController($namespace, $fileName);
        }
    }

    protected static function extractNamespace(string $path): ?string
    {
        $contents = file_get_contents($path);
        if (preg_match('/namespace (.+);/', $contents, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected static function routesFromController(string $namespace, string $fileName): void
    {
        $reflectionClass = new ReflectionClass("$namespace\\$fileName");
        $routeMethods = [];

        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            $routeAttributes = $reflectionMethod->getAttributes(Route::class);

            if (!empty($routeAttributes)) {
                $routeMethods[] = $reflectionMethod;
            }
        }

        foreach ($routeMethods as $routeMethod) {
            $routeAttributes = $routeMethod->getAttributes(Route::class);
            $attribute = end($routeAttributes);
            $arguments = $attribute->getArguments();

            $url = $arguments['url'] ?? $arguments[0];

            if ('/' !== $url) {
                $url = rtrim($url, '/');
                if ('/' !== $url[0]) {
                    $url = "/$url";
                }
            }

            static::setRoute(
                (object) [
                    'url' => $url,
                    'httpMethod' => $arguments['httpMethod'],
                    'class' => $reflectionClass->getName(),
                    'name' => $arguments['name'] ?? $routeMethod->getName(),
                    'method' => $routeMethod->getName(),
                    'middleware' => $arguments['middleware'] ?? null,
                ],
            );
        }
    }

    protected static function setRoute(object $data): void
    {
        // Detect old-style {$param} and normalize to Symfony-style {param}
        if (preg_match('/\{\$/', $data->url)) {
            trigger_error(
                sprintf(
                    'Route "%s" uses deprecated {$param} URL parameter syntax. Use Symfony-style {param} instead.',
                    $data->url,
                ),
                E_USER_DEPRECATED,
            );
            // Normalize: {$id} -> {id}
            $data->url = preg_replace('/\{\$([^}]+)}/', '{$1}', $data->url);
        }

        // Extract params - now always in {param} format
        preg_match_all('/\{([^}]+)}/', $data->url, $matches);
        $routeParams = [];
        foreach ($matches[1] as $match) {
            $routeParams[] = $match;
        }

        $data = [
            'url' => $data->url,
            'class' => $data->class,
            'method' => $data->method,
            'name' => $data->name,
            'httpMethod' => $data->httpMethod,
            'middleware' => $data->middleware,
            'routeParams' => $routeParams,
        ];

        self::checkParams((object) $data);

        self::$routesList[$data['name']] = $data;
    }

    /**
     * Check if the provided data object contains valid parameters for route definition.
     *
     * @param object $data The object containing data for route definition
     *
     * @throws RouteParameterException If the provided data object contains invalid parameters for route definition
     * @throws ReflectionException
     */
    protected static function checkParams(object $data): void
    {
        // Check if middlewares extends Middleware class
        if ($data->middleware) {
            // If middleware is an array or not, make it an array for easy iteration
            $middleware = \is_array($data->middleware) ? $data->middleware : [$data->middleware];

            foreach ($middleware as $m) {
                if (\is_array($m)) {
                    // If middleware is nested array, check each of them
                    foreach ($m as $mm) {
                        if (\is_array($mm)) {
                            // If middleware is another nested array, check each of them
                            foreach ($mm as $mmm) {
                                // Check if middleware extends Middleware class, if not throw an exception
                                if (Middleware::class !== (new ReflectionClass($mmm))->getParentClass()->getName()) {
                                    throw new RuntimeException(
                                        "Middleware for route $data->name in class $data->class must be extended from " . Middleware::class,
                                    );
                                }
                            }
                        } elseif (Middleware::class !== (new ReflectionClass($mm))->getParentClass()->getName()) {
                            // Check if middleware extends Middleware class, if not throw an exception
                            throw new RuntimeException(
                                "Middleware for route $data->name in class $data->class must be extended from " . Middleware::class,
                            );
                        }
                    }
                } elseif (Middleware::class !== (new ReflectionClass($m))->getParentClass()->getName()) {
                    // Check if middleware extends Middleware class, if not throw an exception
                    throw new RuntimeException(
                        "Middleware for route $data->name in class $data->class must be extended from " . Middleware::class,
                    );
                }
            }
        }

        // Check if method parameters are of allowed types
        self::checkMethodParameterTypes($data);

        // Check if HTTP method is allowed
        if (false === \array_key_exists($data->httpMethod, self::ALLOWED_HTTP_METHODS)) {
            throw new InvalidConfigurationException(
                "Undefined HTTP method `$data->httpMethod` for route `$data->name` in class `$data->class`",
            );
        }
    }

    protected static function setCurrentRoute(): bool
    {
        // We need to clear the current route, because it can be set before redirecting to another route
        static::$currentRoute = null;
        self::$routeParams = [];

        /**
         * Values from {@see Router::ALLOWED_HTTP_METHODS} constant }.
         */
        $httpMethod = $_SERVER['REQUEST_METHOD'];

        // HEAD shares GET routes per HTTP spec
        if ('HEAD' === $httpMethod && !\array_key_exists('HEAD', self::$routesByUrl)) {
            $httpMethod = 'GET';
        }

        if (false === \array_key_exists($httpMethod, self::$routesByUrl)) {
            header('Allow: ' . implode(', ', array_keys(self::$routesByUrl)));
            http_response_code(405);
            exit;
        }

        $currentUrl = static::getCurrentRequestUrl();
        $urlHash = hash('sha256', $currentUrl);

        if (ca()->get(sprintf('parsed_url:%s:%s', $httpMethod, $urlHash))) {
            static::$currentRoute = j_decode(ca()->get(sprintf('parsed_url:%s:route:%s', $httpMethod, $urlHash)));
            self::$routeParams = j_decode(ca()->get(sprintf('parsed_url:%s:route_params:%s', $httpMethod, $urlHash)), true);

            return true;
        }

        // Array looks like this: [ '', 'controller', 'method', 'param1', 'param2', ... ]
        $currentUrlArray = explode('/', $currentUrl);

        // Delete first empty element
        array_shift($currentUrlArray);

        // Delete last element if it's empty
        if ('' === end($currentUrlArray)) {
            array_pop($currentUrlArray);
        }

        // Looking for a route with the same url (without parameters)
        foreach (self::$routesByUrl[$httpMethod] as $routeUrl => $route) {
            if ($currentUrl === $routeUrl) {
                static::$currentRoute = (object) self::$routesByUrl[$httpMethod][$currentUrl];

                ca()->setMultiple([
                    sprintf('parsed_url:%s:%s', $httpMethod, $urlHash) => $currentUrl,
                    sprintf('parsed_url:%s:route:%s', $httpMethod, $urlHash) => j_encode(static::$currentRoute),
                    sprintf('parsed_url:%s:route_params:%s', $httpMethod, $urlHash) => j_encode([]),
                ]);

                return true;
            }
        }

        $arr = self::$routesByUrl[$httpMethod];
        $possibleRoutes = [];

        // Looking for a route with the same url (with parameters)
        foreach ($arr as $possibleRouteUrl => $possibleRoute) {
            // Splits the possible route URL into an array of individual components using "/" as a delimiter
            $routeUrlArray = explode('/', $possibleRouteUrl);
            // Removes the first item from the array as it will always be an empty string
            array_shift($routeUrlArray);

            // Check if the length of the current URL and the possible route URL match
            if (\count($currentUrlArray) === \count($routeUrlArray)) {
                $match = true;
                // Check if each component in the possible route URL matches the corresponding component in the current URL
                foreach ($routeUrlArray as $key => $value) {
                    if (false === str_starts_with($value, '{') && $value !== $currentUrlArray[$key]) {
                        $match = false;
                        // If a mismatch is found, break out of the loop
                        break;
                    }
                }

                // If a match is found, store the possible route URL in an array with a count of the number of dynamic components in the URL
                if ($match) {
                    $possibleRoutes[$possibleRouteUrl] = 0;
                    foreach ($routeUrlArray as $value) {
                        if (str_starts_with($value, '{') && str_ends_with($value, '}')) {
                            ++$possibleRoutes[$possibleRouteUrl];
                        }
                    }
                }
            }
        }

        /**
         * Sort all possible routes by number of parameters and save route with the least number of parameters.
         *
         * So, if we have two routes: <b>/product/edit/{$id}</b> and <b>/product/{$category}/{$id}</b>,
         * router will select <b>/product/edit/{$id}</b>
         *
         * Save route parameters to {@see self::$routeParams}
         */
        if (false === empty($possibleRoutes)) {
            // Sort and save route with the least number of parameters
            arsort($possibleRoutes, SORT_NUMERIC);
            static::$currentRoute = (object) $arr[array_key_last($possibleRoutes)];

            // Split the URL of the selected route into an array of components
            $routeUrlArray = explode('/', static::$currentRoute->url);
            array_shift($routeUrlArray);

            // Save the parameters of the route
            foreach ($routeUrlArray as $key => $str) {
                if (str_starts_with($str, '{') && str_ends_with($str, '}')) {
                    self::$routeParams[str_replace(['{', '}'], '', $str)] = $currentUrlArray[$key];
                }
            }

            // Store the selected URL, route and its parameters in a cache
            ca()->setMultiple([
                sprintf('parsed_url:%s:%s', $httpMethod, $urlHash) => $currentUrl,
                sprintf('parsed_url:%s:route:%s', $httpMethod, $urlHash) => j_encode(static::$currentRoute),
                sprintf('parsed_url:%s:route_params:%s', $httpMethod, $urlHash) => j_encode(self::$routeParams),
            ]);
        }

        return null !== static::$currentRoute;
    }

    protected static function getCurrentRequestUrl(): string
    {
        $currentUrl = explode('?', $_SERVER['REQUEST_URI'])[0];
        if (empty($currentUrl)) {
            $currentUrl = '/';
        }

        return $currentUrl;
    }

    protected static function setRequest(): void
    {
        self::$requestData = new Request(
            query: $_GET,
            request: array_merge($_POST, json_decode(file_get_contents('php://input'), true) ?? []),
            cookies: $_COOKIE,
            files: $_FILES,
            server: $_SERVER,
        );
    }

    protected static function setRequestHeaders(): void
    {
        if (\function_exists('apache_request_headers')) {
            foreach (\apache_request_headers() as $headerName => $value) {
                self::$requestData->headers->set($headerName, $value);
            }
        }
    }

    protected static function initializeController(): void
    {
        self::$controller = \App\Kernel::getInstance()->getContainer()->get(static::$currentRoute->class);
    }

    protected static function setRouteParameters(): void
    {
        if (!empty(self::$routeParams)) {
            $reflectionMethod = new ReflectionMethod(static::$currentRoute->class, static::$currentRoute->method);
            $urlPlaceholderNames = array_keys(self::$routeParams);
            $methodParameters = $reflectionMethod->getParameters();

            if (\count($urlPlaceholderNames) !== \count($methodParameters)) {
                throw new RouteParameterException(
                    sprintf(
                        'Method parameters count in the %s::%s route do not match the variables count from route URL!',
                        static::$currentRoute->class,
                        $reflectionMethod->getName(),
                    ),
                );
            }

            $resolvedParams = [];
            foreach ($methodParameters as $index => $reflectionParameter) {
                $urlPlaceholderName = $urlPlaceholderNames[$index];
                $paramValue = self::$routeParams[$urlPlaceholderName];
                $reflectionType = $reflectionParameter->getType();
                $paramType = $reflectionType instanceof ReflectionNamedType ? $reflectionType->getName() : '';

                if (is_a($paramType, AbstractEntity::class, true)) {
                    $entity = $paramType::findOneBy([$urlPlaceholderName => $paramValue]);

                    if (null === $entity && false === $reflectionType->allowsNull()) {
                        self::sendEntityNotFoundResponse();
                    }

                    $resolvedParams[$reflectionParameter->getName()] = $entity;
                } else {
                    settype($paramValue, $paramType);
                    $resolvedParams[$reflectionParameter->getName()] = $paramValue;
                }
            }

            self::$routeParams = $resolvedParams;
        }
    }

    #[NoReturn]
    protected static function sendRouteMethodResponse(): never
    {
        $response = self::$routeMethodResponse;

        $responseEvent = new ResponseEvent(
            self::$kernel,
            self::$requestData,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );
        PhpSfEventDispatcher::dispatch(KernelEvents::RESPONSE, $responseEvent);
        $response = self::$routeMethodResponse = $responseEvent->getResponse();

        if ($response instanceof Response) {
            VarDumper::setHandler(null);
            ob_start(
                function ($b) {
                    if (TEMPLATES_CACHE_ENABLED) {
                        return preg_replace(['/>\s+</'], ['><'], $b);
                    }

                    return $b;
                },
            );

            try {
                $response->send();
            } catch (Throwable $e) {
                ob_end_clean();
                throw new ViewException($e->getMessage(), $e->getCode(), E_ERROR, $e->getFile(), $e->getLine(), $e);
            }
        } else { // if ( $response instanceof JsonResponse or RedirectResponse or plain SymfonyResponse
            $response->send();
        }

        /** @noinspection PhpUnreachableStatementInspection */
        exit;
    }

    private static function checkMethodParameterTypes(object $data): void
    {
        $reflectionMethod = new ReflectionMethod($data->class, $data->method);

        foreach ($reflectionMethod->getParameters() as $index => $reflectionParameter) {
            if ($reflectionParameter->getType() instanceof ReflectionUnionType) {
                throw new RouteParameterException(
                    sprintf(
                        'Method parameter "%s" in the %s::%s route cannot be a union type!',
                        $reflectionParameter->getName(),
                        $data->class,
                        $reflectionMethod->getName(),
                    ),
                );
            }

            $reflectionType = $reflectionParameter->getType();
            self::checkMethodParameterType(
                $reflectionType instanceof ReflectionNamedType ? $reflectionType->getName() : '',
                $reflectionParameter->getName(),
                $data,
                $index,
            );
        }
    }

    private static function checkMethodParameterType(string $type, string $propertyName, object $data, int $paramIndex): void
    {
        switch ($type) {
            case 'int':
            case 'float':
            case 'string':
                break;
            default:
                if (is_a($type, AbstractEntity::class, true)) {
                    $urlPlaceholder = $data->routeParams[$paramIndex] ?? null;
                    if (null !== $urlPlaceholder && !(new ReflectionClass($type))->hasProperty($urlPlaceholder)) {
                        throw new RouteParameterException(
                            sprintf(
                                'Entity "%s" has no property "%s" (from URL placeholder {%s}) used in %s::%s route.',
                                $type,
                                $urlPlaceholder,
                                $urlPlaceholder,
                                $data->class,
                                $data->method,
                            ),
                        );
                    }
                    break;
                }

                throw new InvalidRouteMethodParameterTypeException($type, $propertyName, $data);
        }
    }

    #[NoReturn]
    private static function route(): never
    {
        static::setRequest();
        static::setRequestHeaders();
        self::setHttpMethod(static::$currentRoute->httpMethod);

        static::initializeController();

        static::setRouteParameters();

        PhpSfContext::set(new PhpSfContext(
            static::$currentRoute,
            static::$currentRoute->middleware ?? [],
            self::$kernel,
        ));

        PhpSfEventDispatcher::initialize();

        PhpSfEventDispatcher::dispatch(KernelEvents::REQUEST, new RequestEvent(
            self::$kernel,
            self::$requestData,
            HttpKernelInterface::MAIN_REQUEST,
        ));

        $controllerEvent = new ControllerEvent(
            self::$kernel,
            [self::$controller, static::$currentRoute->method],
            self::$requestData,
            HttpKernelInterface::MAIN_REQUEST,
        );
        PhpSfEventDispatcher::dispatch(KernelEvents::CONTROLLER, $controllerEvent);

        PhpSfEventDispatcher::dispatch(KernelEvents::CONTROLLER_ARGUMENTS, new ControllerArgumentsEvent(
            self::$kernel,
            $controllerEvent,
            array_values(self::$routeParams),
            self::$requestData,
            HttpKernelInterface::MAIN_REQUEST,
        ));

        self::initializeRouteMiddlewares();

        self::initializeRouteMethod();

        static::sendRouteMethodResponse();
    }

    private static function setHttpMethod(string $method): void
    {
        self::$requestData->setMethod($method);
    }

    /**
     * Executes global and route-specific middlewares.
     *
     * @return void|never Returns `void` when all middlewares pass. Behaves as `never` when a middleware returns a
     *                    response and the request flow is terminated after sending that response.
     */
    private static function initializeRouteMiddlewares(): void
    {
        if (!empty(self::$globalMiddlewares)) {
            $global = new MiddlewaresExecutor(self::$globalMiddlewares);

            $globalResult = $global->execute();

            if (true !== $globalResult) {
                $globalResult->send();
            }
        }

        $me = new MiddlewaresExecutor(static::$currentRoute->middleware ?? []);

        $mResult = $me->execute();

        if (true !== $mResult) {
            $mResult->send();
        }
    }

    /**
     * @throws ReflectionException
     * @throws RouteParameterException
     */
    private static function initializeRouteMethod(): void
    {
        $reflectionMethod = new ReflectionMethod(static::$currentRoute->class, static::$currentRoute->method);
        $methodParameters = $reflectionMethod->getParameters();
        $methodParametersCount = \count($methodParameters);
        if (0 === $methodParametersCount) {
            if (0 !== \count(self::$routeParams)) {
                throw new RouteParameterException(
                    sprintf(
                        'Method parameters count in the %s::%s route do not match the variables count from route URL!',
                        static::$currentRoute->class,
                        $reflectionMethod->getName(),
                    ),
                );
            }

            self::$routeMethodResponse = $reflectionMethod->invoke(self::$controller);

            return;
        }

        $methodParametersValues = [];
        foreach ($methodParameters as $reflectionParameter) {
            if (false === \array_key_exists($reflectionParameter->getName(), self::$routeParams)) {
                throw new RouteParameterException(
                    sprintf(
                        'Route url does not contain the "%s" parameter in the %s::%s route!',
                        $reflectionParameter->getName(),
                        static::$currentRoute->class,
                        $reflectionMethod->getName(),
                    ),
                );
            }

            $methodParametersValues[] = self::$routeParams[$reflectionParameter->getName()];
        }

        if ($methodParametersCount !== \count($methodParametersValues) || $methodParametersCount !== \count(self::$routeParams)) {
            throw new RouteParameterException(
                sprintf(
                    'Method parameters count in the %s::%s route do not match the variables count from route URL!',
                    static::$currentRoute->class,
                    $reflectionMethod->getName(),
                ),
            );
        }

        try {
            self::$routeMethodResponse = $reflectionMethod->invokeArgs(self::$controller, $methodParametersValues);
        } catch (ReflectionException $e) {
            throw new RouteParameterException(
                sprintf(
                    'Method parameters in the %s::%s route do not match the variables from route URL!',
                    static::$currentRoute->class,
                    $reflectionMethod->getName(),
                ),
                $e->getCode(),
                $e,
            );
        }
    }

    #[NoReturn]
    private static function sendEntityNotFoundResponse(): never
    {
        self::$routeMethodResponse = str_starts_with(static::$currentRoute->url, '/api/')
            ? ApiResponse::notFound()
            : new Response(status: SymfonyResponse::HTTP_NOT_FOUND);

        static::sendRouteMethodResponse();
    }
}
