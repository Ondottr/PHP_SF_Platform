<?php declare(strict_types=1);

namespace PHP_SF\Framework\Http\Controller\Api;

use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Core\Lang;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

final class ApiLanguageController extends AbstractController
{
    #[Route(url: 'api/lang/change_language', httpMethod: 'POST')]
    public function api_change_language(): JsonResponse
    {
        $lang = r()->request->get( 'lang', false );

        if (!$lang || !\in_array($lang, LANGUAGES_LIST, true)) {
            throw new NotAcceptableHttpException();
        }

        Lang::setCurrentLocale($lang);

        return new JsonResponse(
            [
                'status' => 'ok',
            ],
        );
    }
}
