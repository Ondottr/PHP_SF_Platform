<?php declare( strict_types=1 );

/*
 * Copyright © 2018-2022, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 * granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 * INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 * LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 * RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 * TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace PHP_SF\Framework\Http\Controller\Defaults;

use PHP_SF\System\Core\Response;
use PHP_SF\System\Attributes\Route;
use PHP_SF\Templates\ErrorPage\not_found_page;
use PHP_SF\Templates\ErrorPage\access_denied_page;
use PHP_SF\System\Classes\Abstracts\AbstractController;

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
