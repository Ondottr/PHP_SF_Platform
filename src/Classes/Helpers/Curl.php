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

namespace PHP_SF\System\Classes\Helpers;

/**
 * @deprecated
 */
final class Curl
{

    private static Curl $instance;
    private static bool $debugEnabled = false;

    private function __construct() {}

    /**
     * @deprecated
     */
    public function debug(): self
    {
        self::$debugEnabled = true;

        return self::getInstance();
    }

    /**
     * @deprecated
     */
    public static function getInstance(): Curl
    {
        if ( !isset( self::$instance ) )
            self::setSessionsInstance();

        return self::$instance;
    }

    private static function setSessionsInstance(): void
    {
        self::$instance = new Curl;
    }

    /**
     * @deprecated
     */
    public function get( string $url, array $additionalHeaders = [] ): array|false|null
    {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [
            'Accept:application/json', 'From:' . SERVER_NAME, ...$additionalHeaders,
        ] );

        if ( self::$debugEnabled )
            /** @noinspection ForgottenDebugOutputInspection */
            dd( curl_exec( $ch ) );

        $response = curl_exec( $ch );

        if ( empty( $response ) )
            return false;

        $curlResponse = json_decode( $response, true, 512, JSON_THROW_ON_ERROR );
        curl_close( $ch );

        return $curlResponse;
    }

    /**
     * @deprecated
     */
    public function post( string $url, array $payload, array $additionalHeaders = [] ): array|false
    {
        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $payload, JSON_THROW_ON_ERROR ) );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [
            'Accept:application/json', 'Content-Type:application/json', 'From:' . SERVER_NAME, ...$additionalHeaders,
        ] );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

        if ( self::$debugEnabled )
            /** @noinspection ForgottenDebugOutputInspection */
            dd( curl_exec( $ch ) );

        $response = curl_exec( $ch );

        if ( $response === false )
            return false;

        $curlResponse = json_decode( $response, true, 512, JSON_THROW_ON_ERROR );
        curl_close( $ch );

        return $curlResponse;
    }

}
