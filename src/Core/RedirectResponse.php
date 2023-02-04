<?php declare( strict_types=1 );

namespace PHP_SF\System\Core;

use JetBrains\PhpStorm\Immutable;
use JetBrains\PhpStorm\NoReturn;
use PHP_SF\System\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function function_exists;

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
        #[Immutable] private readonly string $targetUrl,
        #[Immutable] private readonly float|null $requestDataId = null
    )
    {
        parent::__construct();
    }


    /**
     * @noinspection GlobalVariableUsageInspection
     */
    #[NoReturn] public function send(): static
    {
        $key = "{$this->getTargetUrl()}:{$this->getRequestDataId()}";

        $_SERVER['REQUEST_URI']    = $this->getTargetUrl();
        $_SERVER['REQUEST_METHOD'] = Request::METHOD_GET;

        $get = rc()->get("_GET:$key");
        $post = rc()->get("_POST:$key");
        $errors = rc()->get("_ERRORS:$key");
        $messages = rc()->get("_MESSAGES:$key");
        $formData = rc()->get("_FORM_DATA:$key");

        if ($get === null || $post === null || $errors === null)
            throw new HttpException(Response::HTTP_NOT_ACCEPTABLE, 'The page has expired, please return to the previous page!');


        $this->setQuery($get);
        $this->setParams($post);
        $this->setErrors($errors);
        $this->setMessages($messages);
        $this->setFormData($formData);

        $replacedUrl = $this->getTargetUrl();
        if ( empty( $_GET ) === false )
            $replacedUrl .= '?' . http_build_query( $_GET ) ?>

        <script>
            history.replaceState( {}, '', '<?= $replacedUrl ?>' );
        </script>

        <?php
        Router::init();

      /**
       * By default uopz disables the exit opcode, so exit() calls are
       * practically ignored. uopz_allow_exit() allows to control this behavior.
       *
       * @url https://www.php.net/manual/en/function.uopz-allow-exit
       */
      if ( function_exists( 'uopz_allow_exit' ) )
        /** @noinspection PhpUndefinedFunctionInspection */
        uopz_allow_exit( /* Whether to allow the execution of exit opcodes or not.  */ true);

      if ( function_exists( 'fastcgi_finish_request' ) )
            fastcgi_finish_request();
        if ( function_exists( 'litespeed_finish_request' ) )
            /** @noinspection PhpUndefinedFunctionInspection */
            litespeed_finish_request();

        exit( die );

        /** @noinspection PhpUnreachableStatementInspection */
        return $this;
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function getRequestDataId(): float|null
    {
        return $this->requestDataId;
    }

    /**
     * @noinspection GlobalVariableUsageInspection
     */
    private function setFormData( string $formData ): void
    {
        $GLOBALS['form_data'] = [];

        foreach ( j_decode( $formData ) as $key => $value )
            $GLOBALS['form_data'][ $key ] = $value;

    }

    /**
     * @noinspection GlobalVariableUsageInspection
     */
    private function setMessages( string $messages ): void
    {
        $GLOBALS['messages'] = [];

        foreach ( json_decode( $messages, false, 512, JSON_THROW_ON_ERROR ) as $key => $value )
            $GLOBALS['messages'][ $key ] = $value;

    }

    /**
     * @noinspection GlobalVariableUsageInspection
     */
    private function setErrors( string $errors ): void
    {
        $GLOBALS['errors'] = [];

        foreach ( json_decode( $errors, false, 512, JSON_THROW_ON_ERROR ) as $key => $value )
            $GLOBALS['errors'][ $key ] = $value;

    }

    /**
     * @noinspection GlobalVariableUsageInspection
     */
    private function setQuery( string $get ): void
    {
        $_GET = [];

        foreach ( json_decode( $get, true, 512, JSON_THROW_ON_ERROR ) as $key => $value )
            $_GET[ $key ] = $value;

    }

    /**
     * @noinspection GlobalVariableUsageInspection
     */
    private function setParams( string $post ): void
    {
        $_POST = [];

        foreach ( json_decode( $post, true, 512, JSON_THROW_ON_ERROR ) as $key => $value )
            $_POST[ $key ] = $value;

    }

}
