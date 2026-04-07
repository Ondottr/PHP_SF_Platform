<?php declare( strict_types=1 );

namespace PHP_SF\Framework\Http\Middleware;

use PHP_SF\System\Classes\Abstracts\Middleware;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Example API access middleware — guards endpoints based on the client's IP address.
 *
 * Grants access when {@see $_SERVER['REMOTE_ADDR']} is present in the {@see AVAILABLE_HOSTS}
 * constant; returns a 403 JSON response otherwise.
 *
 * @internal Replace with your own "api" middleware that implements the access-control logic
 *           appropriate for your application (e.g. token-based auth, role checks, rate limiting).
 */
final class api_example extends Middleware
{
    public function result(): bool|JsonResponse
    {
        if (
            array_key_exists( 'REMOTE_ADDR', $_SERVER ) &&
            in_array( $_SERVER['REMOTE_ADDR'], AVAILABLE_HOSTS )
        )
            return true;


        return new JsonResponse(
            [ 'error' => _t( 'common.errors.access_denied' ) ], JsonResponse::HTTP_FORBIDDEN
        );
    }
}
