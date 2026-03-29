<?php declare( strict_types=1 );

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
