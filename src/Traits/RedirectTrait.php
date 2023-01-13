<?php declare( strict_types=1 );

namespace PHP_SF\System\Traits;

use InvalidArgumentException;
use PHP_SF\System\Core\RedirectResponse;

trait RedirectTrait
{

    /**
     * @param string     $linkOrRoute
     * If you want to redirect to a route, you can use the routeLink() function.
     * Works with Symfony routes too.
     *  <br />
     * @param array|null $withParams
     * Route parameters optional if <b>$linkOrRoute</b> is a route name
     * and not used if <b>$linkOrRoute</b> is a link.
     *  <br />
     * For example, if you want to redirect to the route named <b>example_route</b>,
     * with url: <b>/example/{$id}</b>, you can use the following code: <br />
     * <i>$this->redirectTo( routeLink( 'example_route', [ 'id' => 1 ] ) )</i> or <br />
     * <i>$this->redirectTo ( '/example/1 ) </i>
     *  <br />
     * @param array|null $get Additional GET parameters. <br />
     * If request already has a GET parameter with the same name, it will be replaced!
     *  <br />
     * @param array|null $post Additional POST parameters. <br />
     * If request already has a POST parameter with the same name, it will be replaced! <br />
     * Works with all HTTP methods, not only POST
     *  <br />
     * @param array|null $errors Errors to be transferred to the next request. <br />
     * To add errors to the current request, you can use the following code: <br />
     * <i>$this->redirectTo( routeLink( 'example_route' ), errors: [ 'Error 1', 'Error 2' ] )</i> or <br />
     * <i>$this->redirectTo( routeLink( 'example_route' ), errors: [
     *      {@see RedirectResponse::ALERT_DANGER} =>'Error 1', {@see RedirectResponse::ALERT_WARNING} => 'Error 2'
     * ] )</i> <br />
     * To get all errors, use: {@see getErrors()} <br />
     * To get a specific error, use: {@see getErrors(RedirectResponse::ALERT_DANGER)}
     *  <br />
     * @param array|null $messages Messages to be transferred to the next request.
     * To add messages to the current request, you can use the following code: <br />
     * <i>$this->redirectTo( routeLink( 'example_route' ), messages: [ 'Message 1', 'Message 2' ] )</i> or <br />
     * <i>$this->redirectTo( routeLink( 'example_route' ), messages: [
     *      {@see RedirectResponse::ALERT_SUCCESS} =>'Message 1', {@see RedirectResponse::ALERT_INFO} => 'Message 2'
     * ] )</i> <br />
     * To get all messages, use: {@see getMessages()} <br />
     * To get a specific message, use: {@see getMessages(RedirectResponse::ALERT_SUCCESS)}
     * <br />
     * @param array|null $formData Additional form data to be transferred to the next request
     * (useful for example when you want to redirect to the same page with the same form data) <br />
     * If request already has a form data with the same name, it will be replaced <br />
     * Works with all HTTP methods, not only POST
     *
     * @return RedirectResponse
     */
    final protected function redirectTo( string $linkOrRoute, array|null $withParams = null, array|null $get = null, array|null $post = null, array|null $errors = null, array|null $messages = null, array|null $formData = null ): RedirectResponse {
        $withParams ??= [];
        $get        ??= [];
        $post       ??= [];
        $errors     ??= [];
        $messages   ??= [];
        $formData   ??= [];

        if ( str_contains( $linkOrRoute, '/' ) ) {
            $rr = $this->toUrl( $linkOrRoute, $get, $post, $errors, $messages, $formData );
        } else {
            $rr = $this->toRoute( $linkOrRoute, $get, $post, $errors, $messages, $formData, $withParams );
        }

        return $rr;
    }

    final protected function redirectBack( array|null $get = null, array|null $post = null, array|null $errors = null, array|null $messages = null, array|null $formData = null ): RedirectResponse
    {
        $get      ??= [];
        $post     ??= [];
        $errors   ??= [];
        $messages ??= [];
        $formData ??= [];

        return $this->toUrl(
            str_replace( $this->request->headers->get( 'origin' ) ?? [ $this->request->headers->get( 'host' ), 'https://', 'http://' ], '', $this->request->headers->get( 'referer' ) ),
            $get, $post, $errors, $messages, $formData
        );
    }


    private function toUrl( string $url, array|null $get = null, array|null $post = null, array|null $errors = null, array|null $messages = null, array|null $formData = null ): RedirectResponse
    {
        $get      ??= [];
        $post     ??= [];
        $errors   ??= [];
        $messages ??= [];
        $formData ??= [];

        return new RedirectResponse(
            $url,
            $this->generateData(
                url:      $url,
                get:      $get,
                post:     $post,
                errors:   $errors,
                messages: $messages,
                formData: array_merge( $formData, isset( $this->request ) ? $this->request->request->all() : [] )
            )
        );
    }

    private function toRoute( string $routeName, array|null $get = null, array|null $post = null, array|null $errors = null, array|null $messages = null, array|null $formData = null, array|null $with = null ): RedirectResponse
    {
        $get      ??= [];
        $post     ??= [];
        $errors   ??= [];
        $messages ??= [];
        $formData ??= [];
        $with     ??= [];

        return $this->toUrl(
            url:      routeLink( $routeName, $with ),
            get:      $get,
            post:     $post,
            errors:   $errors,
            messages: $messages,
            formData: array_merge( $formData, isset( $this->request ) ? $this->request->request->all() : [] )
        );
    }


    private function generateData( string $url, array|null $get = null, array|null $post = null, array|null $errors = null, array|null $messages = null, array|null $formData = null ): float
    {
        $get        ??= [];
        $post       ??= [];
        $errors     ??= [];
        $messages   ??= [];
        $formData   ??= [];
        $redirectId = mt_rand() * mt_rand() / mt_rand();

        $this->validateErrors( $errors );
        $this->validateMessages( $messages );

        $getKey      = "_GET:$url:$redirectId";
        $postKey     = "_POST:$url:$redirectId";
        $errorsKey   = "_ERRORS:$url:$redirectId";
        $messagesKey = "_MESSAGES:$url:$redirectId";
        $formDataKey = "_FORM_DATA:$url:$redirectId";

        rc()->setex( $getKey, 5, json_encode( $get, JSON_THROW_ON_ERROR ) );
        rc()->setex( $postKey, 5, json_encode( $post, JSON_THROW_ON_ERROR ) );
        rc()->setex( $errorsKey, 5, json_encode( $errors, JSON_THROW_ON_ERROR ) );
        rc()->setex( $messagesKey, 5, json_encode( $messages, JSON_THROW_ON_ERROR ) );
        rc()->setex( $formDataKey, 5, json_encode( $formData, JSON_THROW_ON_ERROR ) );

        return $redirectId;
    }

    private function validateErrors( array $errors ): void
    {
        foreach ( $errors as $errorType => $error ) {
            if ( is_int( $errorType ) === false && in_array( $errorType, RedirectResponse::ALERT_TYPES, true ) === false ) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid error type: "%s". Available types: ( %s )',
                        $errorType, implode( ', ', RedirectResponse::ALERT_TYPES )
                    )
                );
            }

            if ( is_string( $error ) === false )
                throw new InvalidArgumentException( 'Error must be a string' );

        }
    }

    private function validateMessages( array $messages ): void
    {
        foreach ( $messages as $messageType => $message ) {
            if ( is_int( $messageType ) === false && in_array( $messageType, RedirectResponse::ALERT_TYPES, true ) === false ) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid message type: "%s". Available types: ( %s )',
                        $messageType, implode( ', ', RedirectResponse::ALERT_TYPES )
                    )
                );
            }

            if ( is_string( $message ) === false )
                throw new InvalidArgumentException( 'Message must be a string' );

        }
    }

}
