<?php declare(strict_types=1);

namespace PHP_SF\System\Traits;

use InvalidArgumentException;
use JetBrains\PhpStorm\ExpectedValues;
use PHP_SF\System\Core\RedirectResponse;

trait RedirectTrait
{
    /**
     * @param string                         $linkOrRoute
     *                                                    If you want to redirect to a route, you can use the routeLink() function.
     *                                                    Works with Symfony routes too.
     *                                                    <br />
     * @param array<string, mixed>|null      $withParams
     *                                                    Route parameters optional if <b>$linkOrRoute</b> is a route name
     *                                                    and not used if <b>$linkOrRoute</b> is a link.
     *                                                    <br />
     *                                                    For example, if you want to redirect to the route named <b>example_route</b>,
     *                                                    with url: <b>/example/{$id}</b>, you can use the following code: <br />
     *                                                    <i>$this->redirectTo( routeLink( 'example_route', [ 'id' => 1 ] ) )</i> or <br />
     *                                                    <i>$this->redirectTo ( '/example/1 ) </i>
     *                                                    <br />
     * @param array<string, string>|null     $get         Additional GET parameters. <br />
     *                                                    If request already has a GET parameter with the same name, it will be replaced!
     *                                                    <br />
     * @param array<string, string>|null     $post        Additional POST parameters. <br />
     *                                                    If request already has a POST parameter with the same name, it will be replaced! <br />
     *                                                    Works with all HTTP methods, not only POST
     *                                                    <br />
     * @param array<int|string, string>|null $errors      Errors to be transferred to the next request. <br />
     *                                                    To add errors to the current request, you can use the following code: <br />
     *                                                    <i>$this->redirectTo( routeLink( 'example_route' ), errors: [ 'Error 1', 'Error 2' ] )</i> or <br />
     *                                                    <i>$this->redirectTo( routeLink( 'example_route' ), errors: [
     *                                                    {@see RedirectResponse::ALERT_DANGER} =>'Error 1', {@see RedirectResponse::ALERT_WARNING} => 'Error 2'
     *                                                    ] )</i> <br />
     *                                                    To get all errors, use: {@see getErrors()} <br />
     *                                                    To get a specific error, use: {@see getErrors(RedirectResponse::ALERT_DANGER)}
     *                                                    <br />
     * @param array<int|string, string>|null $messages    Messages to be transferred to the next request.
     *                                                    To add messages to the current request, you can use the following code: <br />
     *                                                    <i>$this->redirectTo( routeLink( 'example_route' ), messages: [ 'Message 1', 'Message 2' ] )</i> or <br />
     *                                                    <i>$this->redirectTo( routeLink( 'example_route' ), messages: [
     *                                                    {@see RedirectResponse::ALERT_SUCCESS} =>'Message 1', {@see RedirectResponse::ALERT_INFO} => 'Message 2'
     *                                                    ] )</i> <br />
     *                                                    To get all messages, use: {@see getMessages()} <br />
     *                                                    To get a specific message, use: {@see getMessages(RedirectResponse::ALERT_SUCCESS)}
     *                                                    <br />
     * @param array<string, mixed>|null      $formData    Additional form data to be transferred to the next request
     *                                                    (useful for example when you want to redirect to the same page with the same form data) <br />
     *                                                    If request already has a form data with the same name, it will be replaced <br />
     *                                                    Works with all HTTP methods, not only POST
     */
    final protected function redirectTo(
        string $linkOrRoute,
        ?array $withParams = null,
        #[ExpectedValues('string')]
        ?array $get = null,
        #[ExpectedValues('string')]
        ?array $post = null,
        #[ExpectedValues('string')]
        ?array $errors = null,
        #[ExpectedValues('string')]
        ?array $messages = null,
        #[ExpectedValues('string')]
        ?array $formData = null,
    ): RedirectResponse {
        $withParams ??= [];
        $get ??= [];
        $post ??= [];
        $errors ??= [];
        $messages ??= [];
        $formData ??= [];

        if (str_contains($linkOrRoute, '/')) {
            $rr = $this->toUrl($linkOrRoute, $get, $post, $errors, $messages, $formData);
        } else {
            $rr = $this->toRoute($linkOrRoute, $get, $post, $errors, $messages, $formData, $withParams);
        }

        return $rr;
    }

    /**
     * Redirects back to the referring URL, carrying the given payload.
     *
     * @param array<string, string>|null     $get
     * @param array<string, string>|null     $post
     * @param array<int|string, string>|null $errors
     * @param array<int|string, string>|null $messages
     * @param array<string, mixed>|null      $formData
     */
    final protected function redirectBack(?array $get = null, ?array $post = null, ?array $errors = null, ?array $messages = null, ?array $formData = null): RedirectResponse
    {
        $get ??= [];
        $post ??= [];
        $errors ??= [];
        $messages ??= [];
        $formData ??= [];

        if (null === r()->headers->get('referer')) {
            $url = '/';
        } elseif (null !== r()->headers->get('origin')) {
            $url = str_replace(r()->headers->get('origin'), '', r()->headers->get('referer'));
        } else {
            $url = str_replace([r()->headers->get('host'), 'https://', 'http://'], '', r()->headers->get('referer'));
        }

        return $this->toUrl(
            $url,
            $get,
            $post,
            $errors,
            $messages,
            $formData,
        );
    }

    /**
     * Builds a RedirectResponse to an absolute URL.
     *
     * @param array<string, string>|null     $get
     * @param array<string, string>|null     $post
     * @param array<int|string, string>|null $errors
     * @param array<int|string, string>|null $messages
     * @param array<string, mixed>|null      $formData
     */
    private function toUrl(string $url, ?array $get = null, ?array $post = null, ?array $errors = null, ?array $messages = null, ?array $formData = null): RedirectResponse
    {
        $get ??= [];
        $post ??= [];
        $errors ??= [];
        $messages ??= [];
        $formData ??= [];

        return new RedirectResponse(
            $url,
            $this->generateData(
                url: $url,
                get: $get,
                post: $post,
                errors: $errors,
                messages: $messages,
                formData: array_merge($formData, r()->request->all()),
            ),
        );
    }

    /**
     * Builds a RedirectResponse to a named route.
     *
     * @param array<string, string>|null     $get
     * @param array<string, string>|null     $post
     * @param array<int|string, string>|null $errors
     * @param array<int|string, string>|null $messages
     * @param array<string, mixed>|null      $formData
     * @param array<string, mixed>|null      $with
     */
    private function toRoute(string $routeName, ?array $get = null, ?array $post = null, ?array $errors = null, ?array $messages = null, ?array $formData = null, ?array $with = null): RedirectResponse
    {
        $get ??= [];
        $post ??= [];
        $errors ??= [];
        $messages ??= [];
        $formData ??= [];
        $with ??= [];

        return $this->toUrl(
            url: routeLink($routeName, $with),
            get: $get,
            post: $post,
            errors: $errors,
            messages: $messages,
            formData: array_merge($formData, r()->request->all()),
        );
    }

    /**
     * Validates and caches the redirect payload, returning its redirect id.
     *
     * @param array<string, string>|null     $get
     * @param array<string, string>|null     $post
     * @param array<int|string, string>|null $errors
     * @param array<int|string, string>|null $messages
     * @param array<string, mixed>|null      $formData
     */
    private function generateData(string $url, ?array $get = null, ?array $post = null, ?array $errors = null, ?array $messages = null, ?array $formData = null): string
    {
        $get ??= [];
        $post ??= [];
        $errors ??= [];
        $messages ??= [];
        $formData ??= [];
        $hashedUrl = hash('xxh3', $url);
        $redirectId = (string) hrtime(true);

        $this->validateParams($get, $post);
        $this->validateErrors($errors);
        $this->validateMessages($messages);

        ca()->set(":GET:$hashedUrl:$redirectId", j_encode($get), 300);
        ca()->set(":POST:$hashedUrl:$redirectId", j_encode($post), 300);
        ca()->set(":ERRORS:$hashedUrl:$redirectId", j_encode($errors), 300);
        ca()->set(":MESSAGES:$hashedUrl:$redirectId", j_encode($messages), 300);
        ca()->set(":FORM_DATA:$hashedUrl:$redirectId", j_encode($formData), 300);

        return $redirectId;
    }

    /**
     * Asserts every GET and POST parameter value is a string.
     *
     * @param array<string, string> $get
     * @param array<string, string> $post
     */
    private function validateParams(array $get, array $post): void
    {
        foreach ($get as $param) {
            if (false === is_string($param)) {
                throw new InvalidArgumentException('All GET parameters must be strings');
            }
        }

        foreach ($post as $param) {
            if (false === is_string($param)) {
                throw new InvalidArgumentException('All POST parameters must be strings');
            }
        }
    }

    /**
     * Asserts error keys are int or alert types and values are strings.
     *
     * @param array<int|string, string> $errors
     */
    private function validateErrors(array $errors): void
    {
        foreach ($errors as $errorType => $error) {
            if (false === is_int($errorType) && false === in_array($errorType, RedirectResponse::ALERT_TYPES, true)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid error type: "%s". Available types: ( %s )',
                        $errorType,
                        implode(', ', RedirectResponse::ALERT_TYPES),
                    ),
                );
            }

            if (false === is_string($error)) {
                throw new InvalidArgumentException('Error must be a string');
            }
        }
    }

    /**
     * Asserts message keys are int or alert types and values are strings.
     *
     * @param array<int|string, string> $messages
     */
    private function validateMessages(array $messages): void
    {
        foreach ($messages as $messageType => $message) {
            if (false === is_int($messageType) && false === in_array($messageType, RedirectResponse::ALERT_TYPES, true)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid message type: "%s". Available types: ( %s )',
                        $messageType,
                        implode(', ', RedirectResponse::ALERT_TYPES),
                    ),
                );
            }

            if (false === is_string($message)) {
                throw new InvalidArgumentException('Message must be a string');
            }
        }
    }
}
