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

namespace PHP_SF\Framework\Http\Controller\Api;

use PHP_SF\System\Kernel;
use Doctrine\ORM\EntityRepository;
use PHP_SF\System\Attributes\Route;
use PHP_SF\Framework\Http\Middleware\api;
use Doctrine\Persistence\ObjectRepository;
use PHP_SF\Framework\Http\Middleware\auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use function strlen;


class ApiAuthController extends AbstractController
{

    protected EntityRepository|ObjectRepository $userRepository;


    public function __construct( protected ?Request $request )
    {
        parent::__construct( $request );

        $this->userRepository = em()->getRepository( Kernel::getApplicationUserClassName() );
    }

    /** @noinspection MethodShouldBeFinalInspection */
    #[Route( url: 'api/auth/login', httpMethod: 'POST', middleware: api::class )]
    public function api_login(): JsonResponse
    {
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

        if ( isset( $errors ) ) {
            return new JsonResponse(
                [
                    'error' => end( $errors ),
                ], JsonResponse::HTTP_UNAUTHORIZED
            );
        }

        auth::logInUser( $user );

        return new JsonResponse( $user, JsonResponse::HTTP_OK );
    }

    /** @noinspection MethodShouldBeFinalInspection */
    #[Route( url: 'api/auth/register', httpMethod: 'POST', middleware: api::class )]
    public function api_register(): JsonResponse
    {
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
            return new JsonResponse(
                [
                    'error' => end( $errors ),
                ], JsonResponse::HTTP_UNAUTHORIZED
            );


        $user = new ( Kernel::getApplicationUserClassName() )( false );
        $user->setLogin( $login );
        $user->setEmail( $email );
        $user->setPassword( $password );

        if ( ( $errors = $user->validate() ) !== true ) {
            return new JsonResponse(
                [
                    'error' => end( $errors ),
                ], JsonResponse::HTTP_UNAUTHORIZED
            );
        }

        em()->persist( $user );
        em()->flush( $user );

        auth::logInUser( $user );

        return new JsonResponse( $user, JsonResponse::HTTP_OK );
    }

    /** @noinspection MethodShouldBeFinalInspection */
    #[Route( '/api/auth/me', httpMethod: 'GET', middleware: [ api::class, auth::class ] )]
    public function me(): JsonResponse
    {
        return new JsonResponse( auth::user( false ), JsonResponse::HTTP_OK );
    }

    /** @noinspection MethodShouldBeFinalInspection */
    #[Route( url: 'api/auth/logout', httpMethod: 'GET', middleware: [ api::class, auth::class ] )]
    public function api_logout(): JsonResponse
    {
        s()->clear();

        auth::$user = false;

        return new JsonResponse( status: JsonResponse::HTTP_NO_CONTENT );
    }
}
