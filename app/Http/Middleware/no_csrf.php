<?php declare( strict_types=1 );

namespace PHP_SF\Framework\Http\Middleware;

use PHP_SF\System\Classes\Abstracts\Middleware;

/**
 * Marker middleware — opt a route out of automatic CSRF validation.
 * Add to any route that must accept POST without a _token field (e.g. webhooks, internal APIs).
 *
 * Example:
 *   #[Route( url: 'webhook/receive', httpMethod: 'POST', middleware: [ no_csrf::class ] )]
 */
final class no_csrf extends Middleware
{

    protected function result(): bool
    {
        return true;
    }

}
