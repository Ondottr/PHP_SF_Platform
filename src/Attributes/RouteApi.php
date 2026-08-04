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
 * Presence of this attribute anywhere in a controller (on the class or on any routed method)
 * switches the whole class to explicit mode: the deprecated `/api/` URL-prefix detection is
 * ignored for all of its routes, and routes without the attribute are non-API.
 *
 * Without the attribute the legacy prefix-based detection still applies,
 * but it is now deprecated since 3.2 and will be removed in 4.0.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class RouteApi
{
    public function __construct(
        public readonly bool $api = true,
    ) {}
}
