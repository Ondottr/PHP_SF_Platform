<?php declare( strict_types=1 );

namespace PHP_SF\Framework\Http\Controller\Defaults;

use App\Kernel;
use JetBrains\PhpStorm\NoReturn;
use PHP_SF\Framework\Http\Middleware\auth;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Router;
use PHP_SF\System\Traits\JsonResponseHelperTrait;
use PHP_SF\Templates\base;


final class DefaultController extends AbstractController
{
    use JsonResponseHelperTrait;


    #[Route( url: 'base', httpMethod: 'GET' )]
    public function base(): Response
    {
        return $this->render( base::class );
    }

    #[Route( url: 'welcome', httpMethod: 'GET', middleware: auth::class )]
    public function welcome_page(): Response
    {
        return $this->render( base::class );
    }


    /** @noinspection ForgottenDebugOutputInspection */
    #[NoReturn]
    #[Route( url: 'api/routes_list', httpMethod: 'GET' )]
    final public function api_routes_list(): Response
    {
        // restrict to dev mode only
        if ( DEV_MODE === false )
            return $this->forbidden();


        dd(
            array_merge(
                Router::getRoutesList(),
                Kernel::getInstance()
                    ->getContainer()
                    ->get( 'router' )
                    ?->getRouteCollection()
                    ?->all() ?? []
            )
        );
    }

}
