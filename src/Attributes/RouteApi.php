<?php declare(strict_types=1);

namespace PHP_SF\System\Attributes;

use Attribute;

/**
 * Marks a PHP_SF controller, or a single route method, as serving API endpoints.
 *
 * - On a class: every route of the controller is an API endpoint,
 *   unless a method overrides it with `#[RouteApi(false)]`.
 * - On a method: applies to that route only.
 *
 * Routes without any #[RouteApi] attribute are non-API. The legacy `/api/` URL-prefix
 * detection was removed in 4.0 — API endpoints must be declared explicitly with this
 * attribute.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class RouteApi
{
    public function __construct(
        public readonly bool $api = true,
    ) {}
}
