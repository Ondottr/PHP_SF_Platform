<?php
declare( strict_types=1 );


namespace PHP_SF\System\Core;

use JetBrains\PhpStorm\Immutable;
use JetBrains\PhpStorm\NoReturn;
use PHP_SF\System\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use function function_exists;
use function in_array;
use const PHP_SAPI;


final class RedirectResponse extends Response
{

    public const ALERT_PRIMARY   = 'primary';
    public const ALERT_SECONDARY = 'secondary';
    public const ALERT_SUCCESS   = 'success';
    public const ALERT_DANGER    = 'danger';
    public const ALERT_WARNING   = 'warning';
    public const ALERT_INFO      = 'info';

    public const ALERT_TYPES = [
        self::ALERT_PRIMARY,
        self::ALERT_SECONDARY,
        self::ALERT_SUCCESS,
        self::ALERT_DANGER,
        self::ALERT_WARNING,
        self::ALERT_INFO,
    ];


    public function __construct(
        #[Immutable] private string $targetUrl,
        #[Immutable] private ?float $requestDataId = null
    )
    {
        parent::__construct();
    }


    #[NoReturn] public function send(): never
    {
        $_SERVER[ 'REQUEST_URI' ] = $this->getTargetUrl();
        $_SERVER[ 'REQUEST_METHOD' ] = Request::METHOD_GET;
        $key = "{$this->getTargetUrl()}:{$this->getRequestDataId()}";

        $get = rc()->get("_GET:$key");
        $post = rc()->get("_POST:$key");
        $errors = rc()->get("_ERRORS:$key");
        $messages = rc()->get("_MESSAGES:$key");
        $formData = rc()->get("_FORM_DATA:$key");

        if ($get === null || $post === null || $errors === null) {
            throw new HttpException(Response::HTTP_NOT_ACCEPTABLE, 'The page has expired, please return to the previous page!');
        }


        $this->setFormData($formData);
        $this->setMessages($messages);
        $this->setErrors($errors);
        $this->setQuery($get);
        $this->setParams($post);

        ?>

        <script>
            history.replaceState({}, '', '<?= $this->getTargetUrl() ?><?php if (!empty($_GET)) {
                echo '?';
            } ?><?php foreach ($_GET as $key => $value) {
                echo "$key=$value&";
            } ?>');
        </script>

        <?php

        Router::init();


        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (!in_array(PHP_SAPI, [ 'cli', 'phpdbg' ], true)) {
            Response::closeOutputBuffers(0, true);
        }

        die();
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function getRequestDataId(): float|null
    {
        return $this->requestDataId;
    }

    private function setFormData(string $formData): void
    {
        $GLOBALS[ 'form_data' ] = [];

        foreach (j_decode($formData) as $key => $value) {
            $GLOBALS[ 'form_data' ][ $key ] = $value;
        }
    }

    private function setMessages(string $messages): void
    {
        $GLOBALS[ 'messages' ] = [];

        foreach (json_decode($messages, false, 512, JSON_THROW_ON_ERROR) as $key => $value) {
            $GLOBALS[ 'messages' ][ $key ] = $value;
        }
    }

    private function setErrors(string $errors): void
    {
        $GLOBALS[ 'errors' ] = [];

        foreach (json_decode($errors, false, 512, JSON_THROW_ON_ERROR) as $key => $value) {
            $GLOBALS[ 'errors' ][ $key ] = $value;
        }
    }

    private function setQuery(string $get): void
    {
        $_GET = [];
        foreach (json_decode($get, true, 512, JSON_THROW_ON_ERROR) as $key => $value) {
            $_GET[ $key ] = $value;
        }
    }

    private function setParams(string $post): void
    {
        $_POST = [];
        foreach (json_decode($post, true, 512, JSON_THROW_ON_ERROR) as $key => $value) {
            $_POST[ $key ] = $value;
        }
    }

}
