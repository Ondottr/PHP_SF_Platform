# php-simple-framework v4.0.0

## Highlights

Major version bump establishing a **PHP 8.5** / **Symfony 8** baseline and removing the
deprecated behaviours that were announced in 3.x.

## Requirements

- **PHP 8.5** (was 8.3)
- **php-sf-cache ^3.0**

Symfony components remain host-provided (optional) and are compatible with Symfony 8.1.

## Removed (BC breaks)

### 1. `/api/` URL-prefix API detection removed

Until 3.x, a route was implicitly treated as an API endpoint when its URL started with
`/api/`. That heuristic controlled layout wrapping, middleware failure results, and the
entity-not-found response. It was deprecated since 3.2 and is now **removed**.

**Migration:** declare API endpoints explicitly with `#[RouteApi]`:

```php
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Attributes\RouteApi;

#[RouteApi]
final class UserApiController extends AbstractController
{
    #[Route(url: 'users', httpMethod: 'GET')]
    public function list(): JsonResponse { /* ... */ }
}
```

- `#[RouteApi]` on a class marks all of its routes; `#[RouteApi(false)]` on a method overrides it.
- `#[RouteApi]` on a method marks that route only.
- Routes without an attribute are non-API.
- Use `Router::isApiRoute()` to check the resolved flag.

The route cache schema was bumped (`:v4`), so stale entries are re-parsed automatically.

### 2. `{$param}` URL parameter syntax removed

The legacy `{$param}` placeholder syntax was deprecated and is no longer recognized or
normalized.

**Migration:** use Symfony-style `{param}`:

```php
#[Route(url: 'crud/users/{id}/edit', httpMethod: 'GET')]
```

### 3. `Middleware::changeHeaderTemplateClassName()` / `changeFooterTemplateClassName()` removed

The two deprecated shortcut methods (deprecated since 3.0) are removed.

**Migration:** call the kernel setters directly:

```php
Kernel::setHeaderTemplateClassName(header::class);
```

### 4. `TranslatablePropertyName` attribute removed

The deprecated `PHP_SF\System\Attributes\Validator\TranslatablePropertyName` attribute is
removed; the validator already falls back to the property name when no custom name is
provided.

**Migration:** remove the attribute and its `use` import from entity classes.

## Other

- Route cache keys schema-versioned to `:v4`.
- API route return-type validation now applies only to `#[RouteApi]` routes (no prefix fallback).

## Full changelog

- feat: support Symfony 8 / PHP 8.5
- break: remove `/api/` URL-prefix API detection
- break: remove `{$param}` URL parameter syntax
- break: remove `Middleware::changeHeader/FooterTemplateClassName`
- break: remove `TranslatablePropertyName` attribute
- deps: require php 8.5, php-sf-cache ^3.0