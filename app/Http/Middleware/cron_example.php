<?php declare( strict_types=1 );
/*
 * Copyright © 2018-2026, Nations Original Sp. z o.o. <contact@nations-original.com>
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

namespace PHP_SF\Framework\Http\Middleware;

use PHP_SF\System\Classes\Abstracts\Middleware;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Example cron-endpoint access middleware — guards scheduled-task routes by IP address.
 *
 * Grants access when {@see $_SERVER['REMOTE_ADDR']} is present in the {@see AVAILABLE_HOSTS}
 * constant; returns a 403 JSON response otherwise.
 *
 * @internal Replace with your own "cron" middleware that verifies the request originates from
 *           a trusted source — e.g. an IP allowlist, a shared secret token in the request
 *           headers, or both.
 */
final class cron_example extends Middleware
{
    public function result(): bool|JsonResponse
    {
        if (
            array_key_exists( 'REMOTE_ADDR', $_SERVER ) &&
            in_array( $_SERVER['REMOTE_ADDR'], AVAILABLE_HOSTS )
        )
            return true;

        return new JsonResponse(
            [ 'error' => _t( 'access_denied' ) ], JsonResponse::HTTP_FORBIDDEN
        );
    }
}
