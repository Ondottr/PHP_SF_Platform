<?php declare( strict_types=1 );

namespace PHP_SF\System\Traits;

use PHP_SF\System\Core\RedirectResponse;

trait RedirectTrait
{

    /**
     * @noinspection PhpTooManyParametersInspection
     */
    protected function redirectTo(
        string $linkOrRoute,
        array $withParams = [],
        array $get = [],
        array $post = [],
        array $errors = [],
        array $messages = [],
        array $formData = []
    ): RedirectResponse {
        if (str_contains($linkOrRoute, '/')) {
            $rr = $this->toUrl($linkOrRoute, $get, $post, $errors, $messages, $formData);
        } else {
            $rr = $this->toRoute($linkOrRoute, $get, $post, $errors, $messages, $formData, $withParams);
        }

        return $rr;
    }


    /**
     * @noinspection PhpTooManyParametersInspection
     */
    private function toUrl(
        string $url,
        array  $get = [],
        array  $post = [],
        array  $errors = [],
        array  $messages = [],
        array  $formData = []
    ): RedirectResponse {
        return new RedirectResponse(
            targetUrl    : $url,
            requestDataId: $this->generateData(
                url     : $url,
                get     : $get,
                post    : $post,
                errors  : $errors,
                messages: $messages,
                formData: array_merge(
                    $formData,
                    isset($this->request) ? $this->request->request->all() : []
                )
            )
        );
    }

    /**
     * @noinspection PhpTooManyParametersInspection
     */
    private function generateData(
        string $url,
        array  $get = [],
        array  $post = [],
        array  $errors = [],
        array  $messages = [],
        array  $formData = [],
    ): float {
        $redirectId = mt_rand() * mt_rand() / mt_rand();

        $getKey = "_GET:$url:$redirectId";
        $postKey = "_POST:$url:$redirectId";
        $errorsKey = "_ERRORS:$url:$redirectId";
        $messagesKey = "_MESSAGES:$url:$redirectId";
        $formDataKey = "_FORM_DATA:$url:$redirectId";

        rc()->setex($getKey, 5, json_encode($get, JSON_THROW_ON_ERROR));
        rc()->setex($postKey, 5, json_encode($post, JSON_THROW_ON_ERROR));
        rc()->setex($errorsKey, 5, json_encode($errors, JSON_THROW_ON_ERROR));
        rc()->setex($messagesKey, 5, json_encode($messages, JSON_THROW_ON_ERROR));
        rc()->setex($formDataKey, 5, json_encode($formData, JSON_THROW_ON_ERROR));

        return $redirectId;
    }


    /**
     * @noinspection PhpTooManyParametersInspection
     */
    private function toRoute(
        string $routeName,
        array  $get = [],
        array  $post = [],
        array  $errors = [],
        array  $messages = [],
        array  $formData = [],
        array  $with = []
    ): RedirectResponse {
        return $this->toUrl(
            url     : routeLink($routeName, $with),
            get     : $get,
            post    : $post,
            errors  : $errors,
            messages: $messages,
            formData: array_merge(
                $formData,
                isset($this->request) ? $this->request->request->all() : []
            )
        );
    }
}
