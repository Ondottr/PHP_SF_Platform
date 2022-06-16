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

namespace PHP_SF\Framework\Http\Controller;

use PHP_SF\System\Kernel;
use PHP_SF\System\Core\Response;
use Doctrine\ORM\EntityRepository;
use PHP_SF\System\Attributes\Route;
use PHP_SF\Templates\Auth\login_page;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\Templates\Auth\register_page;
use Doctrine\Persistence\ObjectRepository;
use PHP_SF\Framework\Http\Middleware\auth;
use Symfony\Component\HttpFoundation\Request;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use function strlen;


class AuthController extends AbstractController
{

    protected EntityRepository|ObjectRepository $userRepository;


    public function __construct( protected ?Request $request )
    {
        parent::__construct( $request );

        $this->userRepository = em()->getRepository( Kernel::getApplicationUserClassName() );
    }


    #[Route( url: 'auth/login', httpMethod: 'GET' )]
    public function login_page(): Response|RedirectResponse
    {
        if ( auth::isAuthenticated() )
            return $this->redirectTo(
                routeLink( 'home_page' )
            );

        return $this->render( login_page::class );
    }

    #[Route( url: 'auth/login', httpMethod: 'POST' )]
    public function login_handler(): RedirectResponse
    {
        if ( auth::isAuthenticated() )
            return $this->redirectTo(
                routeLink( 'home_page' )
            );

        $email    = trim( htmlspecialchars( $this->request->request->get( 'email' ) ) );
        $password = trim( htmlspecialchars( $this->request->request->get( 'password' ) ) );


        if ( empty( $email ) || empty( $password ) )
            $errors[] = _t( 'email_and_password_cannot_be_empty' );

        if ( ( $user = $this->userRepository->findOneBy( [ 'email' => $email ] ) ) === null )
            if ( ( $user = $this->userRepository->findOneBy( [ 'login' => $email ] ) ) === null )
                $errors[] = _t( 'user_with_this_email_not_found' );


        if ( $user !== null )
            if ( password_verify( $password, $user->getPassword() ) === false )
                $errors[] = _t( 'wrong_password' );


        if ( isset( $errors ) )
            return $this->redirectTo( 'login_page', errors: $errors );


        auth::logInUser( $user );

        return $this->redirectTo( 'welcome_page' );
    }


    #[Route( url: 'auth/register', httpMethod: 'GET' )]
    public function register_page(): Response|RedirectResponse
    {
        if ( auth::isAuthenticated() )
            return $this->redirectTo(
                routeLink( 'home_page' )
            );

        return $this->render( register_page::class );
    }

    #[Route( url: 'auth/register', httpMethod: 'POST' )]
    public function register_handler(): RedirectResponse
    {
        if ( auth::isAuthenticated() )
            return $this->redirectTo(
                routeLink( 'home_page' )
            );

        $login    = trim( htmlspecialchars( $this->request->request->get( 'login' ) ) );
        $password = trim( htmlspecialchars( $this->request->request->get( 'password' ) ) );
        $email    = trim( htmlspecialchars( $this->request->request->get( 'email' ) ) );
        $accept   = trim( htmlspecialchars( $this->request->request->get( 'accept' ) ) );

        if ( empty( $password ) || empty( $email ) )
            $errors[] = _t( 'email_and_password_cannot_be_empty' );

        if ( ( $passwordLength = strlen( $password ) ) < 6 || $passwordLength > 50 )
            $errors[] = _t( 'range_validation_error', 'password', 6, 50 );

        if ( $accept !== 'on' )
            $errors[] = _t( 'accept_checkbox_error' );

        if ( $this->userRepository->findOneBy( [ 'email' => $email ] ) !== null )
            $errors[] = _t( 'user_email_property_with_this_value_already_exists' );

        if ( $this->userRepository->findOneBy( [ 'login' => $login ] ) !== null )
            $errors[] = _t( 'user_login_property_with_this_value_already_exists' );


        if ( isset( $errors ) )
            return $this->redirectTo( 'register_page', errors: $errors );


        $user = new ( Kernel::getApplicationUserClassName() )( false );
        $user->setLogin( $login );
        $user->setEmail( $email );
        $user->setPassword( $password );

        if ( $user->validate() !== true )
            return $this->redirectTo( 'register_page', errors: $user->getValidationErrors() );


        em()->persist( $user );
        em()->flush( $user );

        auth::logInUser( $user );

        return $this->redirectTo( 'welcome_page' );
    }
}
