<?php declare(strict_types=1);
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

namespace PHP_SF\System\Traits;

use PHP_SF\System\Router;
use JetBrains\PhpStorm\NoReturn;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Core\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

trait ControllerTrait
{

    use RedirectTrait;


    public function __construct(protected ?Request $request) { }

    #[NoReturn] protected function getRedirect(
        string $linkOrRoute,
        array  $withParams = [],
        array  $get = [],
        array  $post = [],
        array  $errors = [],
        array  $messages = [],
        array  $formData = []
    ): RedirectResponse
    {
        $rr = $this->redirectTo($linkOrRoute, $withParams, $get, $post, $errors, $messages, $formData);

        $rr->send();
    }

    #[NoReturn] protected function getResponse(string $view, array $data = []): Response
    {
        Router::$currentRoute = (object)[
            'name' => $this->request->get('_route'),
        ];

        $response = new Response(view: new $view($data));

        $response->send();
    }
}
