<?php /** @noinspection MethodShouldBeFinalInspection @noinspection PhpUnused */
declare( strict_types=1 );

namespace PHP_SF\Framework\Http\Controller\Api;

use Memcached;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiCacheController extends AbstractController
{

    #[Route( url: 'api/cache_clear/all', httpMethod: 'GET' )]
    public function api_clear_all_cache(): JsonResponse
    {
        if( function_exists( 'apcu_enabled' ) && apcu_enabled() )
            aca()->clear();
        
        rca()->clear();
        if ( class_exists( Memcached::class ) )
            mca()->clear();

        return new JsonResponse( [ 'status' => 'ok' ], JsonResponse::HTTP_OK );
    }

}
