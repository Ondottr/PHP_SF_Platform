<?php declare( strict_types=1 );

namespace PHP_SF\Framework\Http\Controller\Api;

use PHP_SF\System\Core\Lang;
use PHP_SF\System\Attributes\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use function in_array;

final class ApiLanguageController extends AbstractController
{

    #[Route( url: 'api/lang/change_language', httpMethod: 'POST' )]
    public function api_change_language(): JsonResponse
    {
        $lang = $this->request->request->get( 'lang', false );

        if ( !$lang || !in_array( $lang, LANGUAGES_LIST, true ) )
            throw new NotAcceptableHttpException;

        Lang::setCurrentLocale( $lang );

        return new JsonResponse(
            [
                'status' => 'ok',
            ] );
    }

}
