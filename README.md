# PHP Simple Framework

A lightweight, modern PHP 8.3+ framework built on top of Symfony and Doctrine, designed for rapid web application development with clean attribute-based configuration.

**Package:** `nations-original/php-simple-framework`
**Namespace:** `PHP_SF\System\`
**License:** ISC
**Author:** Dmytro Dyvulskyi — CEO & Lead Developer, Nations Original

---

## Documentation

Full documentation is available at **[wiki.nations-original.com/framework](https://wiki.nations-original.com/framework)**.

The wiki covers every aspect of the framework in depth — from installation and first steps to advanced topics like dual-kernel architecture, caching, message queues, and testing. If you are new to the framework, start there.

| Section                                                                        | Topics                                                                  |
|--------------------------------------------------------------------------------|-------------------------------------------------------------------------|
| [Getting Started](https://wiki.nations-original.com/framework/getting-started) | Installation, constants, creating your first page                       |
| [Core](https://wiki.nations-original.com/framework/core)                       | Lifecycle, controllers, routing, middleware, views, sessions, redirects |
| [Dual Kernel](https://wiki.nations-original.com/framework/core/dual-kernel)    | PHP_SF kernel + Symfony kernel coexistence, bootstrap order             |
| [Data & Persistence](https://wiki.nations-original.com/framework/data)         | Entities, repositories, validation, cache, fixtures, enums              |
| [Infrastructure](https://wiki.nations-original.com/framework/infrastructure)   | Docker, Redis, RabbitMQ, template cache, kernel config                  |
| [Supporting Features](https://wiki.nations-original.com/framework/supporting)  | Helper functions, translation, events, CRUD controller                  |
| [Development & Testing](https://wiki.nations-original.com/framework/dev)       | PHPUnit, dev mode                                                       |
| [Frontend](https://wiki.nations-original.com/framework/frontend)               | Asset building with Webpack                                             |

---

## Getting Started

This package is the framework core. To start a new project, use the template:

```bash
composer create-project nations-original/php-simple-framework-template my-app
```

To add the framework to an existing Symfony project:

```bash
composer require nations-original/php-simple-framework
```

Requires PHP **8.3+** with strict typing, plus `ext-apcu`, `ext-redis`. See [Installation guide](https://wiki.nations-original.com/framework/getting-started/installation) for full prerequisites.

---

## Overview

PHP Simple Framework sits alongside Symfony's kernel inside the same PHP process. When a request comes in, the framework's router tries to match it first using its own attribute-based routing. If no match is found, the request falls through to Symfony. This gives you the performance and simplicity of a hand-rolled framework while retaining the full power of the Symfony ecosystem (DI container, console, bundles, Doctrine) when you need it.

See [Dual Kernel Architecture](https://wiki.nations-original.com/framework/core/dual-kernel) for a detailed explanation.

### Request Lifecycle

1. `public/index.php` bootstraps autoloader, global functions, and constants
2. `PHP_SF\System\Kernel` boots — registers controllers, translations and templates
3. `Router` resolves the URL against registered `#[Route]` attributes (cached in Redis)
4. Middleware chain executes (pre-dispatch)
5. Controller method is called, producing a `Response`, `RedirectResponse`, or JSON
6. Response is sent; output buffer is flushed

Full 17-step trace: [Request & Response Lifecycle](https://wiki.nations-original.com/framework/core/lifecycle).

---

## Core Concepts

### Routing

Routes are declared with PHP 8 attributes directly on controller methods. No separate routing file needed.

```php
use PHP_SF\System\Attributes\Route;

#[Route(url: 'posts/{id}', httpMethod: 'GET', name: 'post_show')]
public function show(int $id): Response { ... }
```

- URL parameters use `{param}` syntax with automatic type casting
- Named routes for URL generation via `routeLink('post_show', ['id' => 42])`
- Route-level middleware assignment
- Routes are cached in Redis after first resolution

Full reference: [Routing](https://wiki.nations-original.com/framework/core/routing).

### Controllers

Extend `AbstractController` and return a `Response`, JSON, or redirect.

```php
use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Attributes\Route;

class PostController extends AbstractController
{
    #[Route(url: 'posts', httpMethod: 'GET', name: 'post_index')]
    public function post_list_view(): Response
    {
        return $this->render(new post_list_view());
    }

    #[Route(url: 'api/posts', httpMethod: 'GET', name: 'api_post_index')]
    public function api_posts(): Response
    {
        return $this->ok(['posts' => [...]]);
    }
}
```

Available JSON shortcut methods: `ok()`, `created()`, `badRequest()`, `unauthorized()`, `forbidden()`, `notFound()`, `conflict()`, `unprocessableEntity()`, etc.

Full reference: [Controllers](https://wiki.nations-original.com/framework/core/controllers).

### Views

Views are PHP classes that extend `AbstractView` and implement a `show()` method. Output buffering captures the rendered HTML; the framework injects the configured header and footer automatically.

Full reference: [Views](https://wiki.nations-original.com/framework/core/views).

### Middleware

Middleware files extend `Middleware` and are referenced by name on `#[Route]` attributes. Middleware can be composed with:

- `MiddlewareAll` — all listed middleware must pass
- `MiddlewareAny` — at least one must pass
- `MiddlewareCustom` — custom composition logic

Full reference: [Middleware](https://wiki.nations-original.com/framework/core/middleware).

### Entities

Entities extend `AbstractEntity` and use Doctrine ORM attributes alongside PHP_SF validation attributes.

```php
use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHP_SF\System\Attributes\Validator as Validate;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
class Post extends AbstractEntity
{
    #[ORM\Column]
    #[Validate\Length(min: 3, max: 255)]
    #[TranslatablePropertyName]
    protected string $title;
}
```

- Properties must be `protected` (AbstractEntity uses reflection)
- `#[TranslatablePropertyName]` enables localized validation error messages
- Doctrine lifecycle callbacks via `App/DoctrineLifecycleCallbacks/`

Full reference: [Entities](https://wiki.nations-original.com/framework/data/entities) · [Validation](https://wiki.nations-original.com/framework/data/validation).

### Multi-Database & EntityManager

The framework supports multiple simultaneous Doctrine entity managers. Use the `em()` helper with a connection name — never rely on the default EM.

```php
em('postgresql')->getRepository(User::class)->find($id);
em('mysql')->persist($post);
em('mariadb')->flush();
```

Full reference: [Repositories](https://wiki.nations-original.com/framework/data/repositories).

### Caching

The `ca()` helper returns the best available cache adapter automatically. Specific adapters are also accessible directly.

```php
ca()->set('key', $value, 3600);   // auto-selects APCu → Redis → Memcached
aca()->set('key', $value);        // APCu cache adapter
rca()->set('key', $value);        // Redis cache adapter
mca()->set('key', $value);        // Memcached cache adapter
```

Full reference: [Cache](https://wiki.nations-original.com/framework/data/cache) · [Redis](https://wiki.nations-original.com/framework/infrastructure/redis).

### Helper Functions

Global helper functions cover the most common framework operations without requiring dependency injection.

| Function                                 | Purpose                                         |
|------------------------------------------|-------------------------------------------------|
| `em(string $name)`                       | Get a Doctrine EntityManager by connection name |
| `qb(string $name)`                       | Get a Doctrine QueryBuilder                     |
| `ca()`                                   | Get the auto-selected cache adapter             |
| `rca()` / `aca()` / `mca()`              | Get Redis / APCu / Memcached adapter            |
| `rc()`                                   | Get the Redis client                            |
| `s()`                                    | Get the current Session                         |
| `routeLink(string $name, array $params)` | Generate a URL from a named route               |
| `_t(string $key, array $parameters = [])` | Translate a string (ICU `{param}` placeholders) |

Full reference: [Helper Functions](https://wiki.nations-original.com/framework/supporting/helpers).

### Translation

Translation files are YAML in `translations/` (app) and `Platform/translations/` (framework), using ICU `{param}` syntax. Use `_t('key')` or `_t('key', ['param' => $value])` anywhere in templates or controllers.

Full reference: [Translation](https://wiki.nations-original.com/framework/supporting/translation).

### Message Queues (RabbitMQ)

RabbitMQ publisher and consumer are available via `PHP_SF\System\Database\RabbitMQ` and `RabbitMQConsumer`. Queue names are defined in `App/Enums/Amqp/QueueEnum.php`.

Full reference: [RabbitMQ](https://wiki.nations-original.com/framework/infrastructure/rabbitmq).

---

## Namespace Structure

| Namespace           | Path         | Purpose                                                    |
|---------------------|--------------|------------------------------------------------------------|
| `PHP_SF\System\`    | `src/`       | Framework core (routing, kernel, cache, base classes)      |
| `PHP_SF\Framework\` | `app/`       | Framework-provided app skeleton (controllers, middleware)  |
| `PHP_SF\Templates\` | `templates/` | Built-in template files                                    |
| `PHP_SF\Tests\`     | `tests/`     | Framework test suite                                       |

---

## License

ISC — see [LICENSE.MD](LICENSE.MD).
