<?php declare( strict_types=1 );

namespace PHP_SF\Framework\Http\Controller\Defaults;

use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Core\Response;
use PHP_SF\Templates\ErrorPage\access_denied_page;
use PHP_SF\Templates\ErrorPage\not_found_page;

class ErrorPageController extends AbstractController
{

    /**
     * @return Response
     *
     * @noinspection PhpUnused
     * @noinspection MethodShouldBeFinalInspection
     */
    #[Route( url: 'access_denied', httpMethod: 'GET' )]
    public function access_denied_page(): Response
    {
        return $this->render( access_denied_page::class );
    }

    /**
     * @return Response
     *
     * @noinspection PhpUnused
     * @noinspection MethodShouldBeFinalInspection
     */
    #[Route( url: 'page_not_found', httpMethod: 'GET' )]
    public function not_found_page(): Response
    {
        return $this->render( not_found_page::class );
    }
}
