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

use PHP_SF\System\Core\Lang;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\Framework\Http\Middleware\auth;
use PHP_SF\Templates\SettingsPage\change_language_page;
use PHP_SF\System\Classes\Abstracts\AbstractController;

use function in_array;

final class SettingsController extends AbstractController
{

    #[Route( url: 'settings/change_language', httpMethod: 'GET', middleware: auth::class )]
    public function change_language_page(): Response
    {
        return $this->render( change_language_page::class );
    }


    #[Route( url: 'settings/change_language', httpMethod: 'POST', middleware: auth::class )]
    public function change_language_post_handler(): RedirectResponse
    {
        $lang = $this->request->request->get( 'lang', false );

        if ( !$lang || !in_array( $lang, LANGUAGES_LIST, true ) )
            return $this->redirectTo( 'change_language_page', errors: [ _t( 'something_went_wrong_try_again' ) ] );

        Lang::setCurrentLocale( $lang );

        return $this->redirectBack();
    }


    #[Route( url: 'settings/change_language/{$lang}', httpMethod: 'GET' )]
    public function change_language_get_handler( string $lang ): RedirectResponse
    {
        Lang::setCurrentLocale( $lang );

        return $this->redirectBack();
    }


}
