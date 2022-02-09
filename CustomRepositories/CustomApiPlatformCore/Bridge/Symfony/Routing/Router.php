<?php declare( strict_types=1 );

namespace ApiPlatform\Core\Bridge\Symfony\Routing;

use App\Http\Middleware\example_middleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use ApiPlatform\Core\Api\UrlGeneratorInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface as UrlGeneratorInterfaceAlias;
use function strlen;


final class Router implements RouterInterface, UrlGeneratorInterface
{
    public const CONST_MAP = [
        UrlGeneratorInterface::ABS_URL  => UrlGeneratorInterfaceAlias::ABSOLUTE_URL,
        UrlGeneratorInterface::ABS_PATH => UrlGeneratorInterfaceAlias::ABSOLUTE_PATH,
        UrlGeneratorInterface::REL_PATH => UrlGeneratorInterfaceAlias::RELATIVE_PATH,
        UrlGeneratorInterface::NET_PATH => UrlGeneratorInterfaceAlias::NETWORK_PATH,
    ];

    private RouterInterface $router;
    private int             $urlGenerationStrategy;

    public function __construct( RouterInterface $router, int $urlGenerationStrategy = self::ABS_PATH )
    {
        $this->router                = $router;
        $this->urlGenerationStrategy = $urlGenerationStrategy;
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteCollection(): RouteCollection
    {
        return $this->router->getRouteCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function match( string $pathInfo ): array
    {
        $baseContext = $this->router->getContext();
        $baseUrl     = $baseContext->getBaseUrl();
        if ( str_starts_with( $pathInfo, $baseUrl ) )
            $pathInfo = substr( $pathInfo, strlen( $baseUrl ) );

        $request = Request::create( $pathInfo, 'GET', [], [], [], [ 'HTTP_HOST' => $baseContext->getHost() ] );
        try {
            $context = ( new RequestContext() )->fromRequest( $request );
        } catch ( RequestExceptionInterface ) {
            throw new ResourceNotFoundException( 'Invalid request context.' );
        }

        if ( ( $uri = $request->getRequestUri() ) !== '/api' && $uri !== '/api/' && str_starts_with( $uri, '/api' ) )
            new example_middleware( $request );

        $context->setPathInfo( $pathInfo );
        $context->setScheme( $baseContext->getScheme() );
        $context->setHost( $baseContext->getHost() );

        try {
            $this->router->setContext( $context );

            return $this->router->match( $request->getPathInfo() );
        } finally {
            $this->router->setContext( $baseContext );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): RequestContext
    {
        return $this->router->getContext();
    }

    /**
     * {@inheritdoc}
     */
    public function setContext( RequestContext $context ): void
    {
        $this->router->setContext( $context );
    }

    /**
     * {@inheritdoc}
     */
    public function generate( $name, $parameters = [], $referenceType = self::ABS_PATH ): string
    {
        return $this->router->generate(
            $name,
            $parameters,
            self::CONST_MAP[ $referenceType ?? $this->urlGenerationStrategy ]
        );
    }

}
