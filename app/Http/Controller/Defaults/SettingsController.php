<?php declare( strict_types=1 );

namespace PHP_SF\Framework\Http\Controller\Defaults;

use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Core\Lang;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Core\Response;
use PHP_SF\Templates\SettingsPage\change_language_page;

use function in_array;

final class SettingsController extends AbstractController
{

    #[Route( url: 'settings/change_language', httpMethod: 'GET' )]
    public function change_language_page(): Response
    {
        return $this->render( change_language_page::class );
    }


    #[Route( url: 'settings/change_language', httpMethod: 'POST' )]
    public function change_language_post_handler(): RedirectResponse
    {
        $lang = $this->request->request->get( 'lang', false );

        if ( !$lang || !in_array( $lang, LANGUAGES_LIST, true ) )
            return $this->redirectTo( 'change_language_page', errors: [ _t( 'settings.change_language.error.invalid_language' ) ] );

        Lang::setCurrentLocale( $lang );

        return $this->redirectBack();
    }


    #[Route( url: 'settings/change_language/{lang}', httpMethod: 'GET' )]
    public function change_language_get_handler( string $lang ): RedirectResponse
    {
        Lang::setCurrentLocale( $lang );

        return $this->redirectBack();
    }


}
