<?php declare( strict_types=1 );

namespace PHP_SF\Framework\Routing;

use PHP_SF\System\Router;
use RuntimeException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Loads PHP_SF framework routes into Symfony's router so that Codeception
 * functional tests (which use Symfony's HttpKernel) can match those routes.
 *
 * Referenced in config/routes.yaml under "when@test" so it only affects the
 * test environment; production requests are handled by Router::init() directly.
 */
final class PhpSfRouteLoader extends Loader
{

    private bool $loaded = false;

    public function __construct( private readonly string $controllersDir )
    {
        parent::__construct();
    }

    public function load( mixed $resource, ?string $type = null ): RouteCollection
    {
        if ( $this->loaded )
            throw new RuntimeException( 'PHP_SF routes have already been loaded — do not add this loader more than once.' );

        $this->loaded = true;

        if ( empty( Router::getRoutesList() ) ) {
            Router::addControllersDirectory( $this->controllersDir );
            Router::loadRoutesOnly();
        }

        $collection = new RouteCollection();

        foreach ( Router::getRoutesList() as $routeName => $route ) {
            $collection->add(
                $routeName,
                ( new Route( $route['url'] ) )
                    ->setMethods( [ $route['httpMethod'] ] )
                    ->addDefaults( [
                        '_controller'        => $route['class'] . '::' . $route['method'],
                        '_php_sf_url'        => $route['url'],
                        '_php_sf_middleware' => $route['middleware'] ?? [],
                    ] )
            );
        }

        return $collection;
    }

    public function supports( mixed $resource, ?string $type = null ): bool
    {
        return $type === 'php_sf';
    }

}
