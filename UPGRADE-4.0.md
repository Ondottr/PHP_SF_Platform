# Upgrade from 3.x to 4.0

## The `/api/` URL-prefix API detection has been removed

Until PHP_SF 3.x, a route was implicitly treated as an API endpoint when its URL started
with `/api/`. That heuristic controlled:

- layout wrapping in `PHP_SF\System\Core\Response` (header/footer chrome skipped for API routes),
- the failure result of middlewares (`ApiResponse::forbidden()` instead of a redirect back),
- the entity-not-found response (`ApiResponse::notFound()` instead of an HTML 404 page).

Since 3.2 this behavior is deprecated (`trigger_deprecation()` fires when the prefix heuristic
classifies a route). In 4.0 the heuristic is gone, and routes are non-API unless declared
otherwise.

### Migration

Declare API endpoints explicitly with the `PHP_SF\System\Attributes\RouteApi` attribute:

```php
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Attributes\RouteApi;

// Every route of the controller is an API endpoint
#[RouteApi]
final class UserApiController extends AbstractController
{
    #[Route(url: 'users', httpMethod: 'GET')]
    public function list(): JsonResponse
    {
        // ...
    }

    // Single opt-out inside an API controller (e.g. an HTML form)
    #[RouteApi(false)]
    #[Route(url: 'users/form', httpMethod: 'GET')]
    public function form(): Response
    {
        // ...
    }
}

final class MixedController extends AbstractController
{
    // Method-level: only this route is an API endpoint
    #[RouteApi]
    #[Route(url: 'status', httpMethod: 'GET')]
    public function status(): JsonResponse
    {
        // ...
    }
}
```

Semantics:

- `#[RouteApi]` on a class marks all of its routes; `#[RouteApi(false)]` on a method overrides it.
- `#[RouteApi]` on a method marks that route only.
- The presence of the attribute anywhere in a controller (class or any routed method) disables
  the URL-prefix fallback for the whole class: unmarked routes are non-API.
- Use `Router::isApiRoute()` to check the resolved flag of the current (or a given) route.

Route cache keys (`cache:routes_list`, `cache:routes_by_url_list`, and the per-request
`parsed_url:*` entries) are schema-versioned from 3.2. Entries written by an earlier version
are ignored automatically and routes are re-parsed, so the new `api` flag is picked up on the
first request after deploy — no manual cache flush is needed for correct classification.

## API route return types are validated at route-registration time

Since 3.2, every route classified as API (`#[RouteApi]`, or the deprecated `/api/` URL-prefix
fallback) has its declared return type validated when routes are parsed. An API route method
may only declare `PHP_SF\System\Core\Response` or `Symfony\Component\HttpFoundation\JsonResponse`
(subclasses included — e.g. `ApiResponse`); anything else throws
`PHP_SF\System\Classes\Exception\InvalidRouteReturnTypeException` at boot.

This exists because a `RedirectResponse` returned from an API route emits the redirect's
`history.replaceState()` JavaScript into the JSON body, corrupting the response — and any
non-response return type cannot be sent to the client at all.

Migration for violating routes:

- Return `ApiResponse::error()` / a `JsonResponse` error instead of redirecting, or
- drop `#[RouteApi]` if the endpoint is actually a page route, or
- split the behaviour: one API endpoint returning JSON, one page endpoint handling the redirect.

Methods without a declared return type are skipped by the validation, but cannot produce a
sendable response — declare `Response` or `JsonResponse` explicitly.
