# php-exceptions-laravel

Laravel wiring + JSON renderer for [`hradigital/php-exceptions`](https://packagist.org/packages/hradigital/php-exceptions).

The base library ships platform-agnostic domain exceptions (`AbstractBaseException` and the `Client/`+`Server/` trees, aligned with HTTP 4xx/5xx semantics). This package translates them into a uniform JSON response at the HTTP boundary and auto-registers itself with Laravel's exception handler, but **only for API requests** — browser requests still fall through to Laravel's default error pages.

| Package          | `hradigital/php-exceptions-laravel`                      |
| ---------------- | -------------------------------------------------------- |
| Namespace        | `HraDigital\Components\ExceptionRenderer`                |
| Requires         | PHP `^8.1`, `hradigital/php-exceptions` `^1.0`           |
| Laravel          | `^10.0` &middot; `^11.0` &middot; `^12.0`                |
| License          | GPL-3.0-or-later                                         |
| Status           | **Private** &mdash; not yet published to Packagist       |

---

## Installation

> The repository is currently private. Until it is published, install from its private GitHub source via a Composer VCS entry.

In the consuming project's `composer.json`:

```json
{
    "repositories": [
        { "type": "vcs", "url": "git@github.com:HRADigital/php-exceptions-laravel.git" }
    ]
}
```

Then:

```bash
composer require hradigital/php-exceptions-laravel:dev-master
```

Composer will read GitHub auth from `auth.json` or `COMPOSER_AUTH`. Once the package is on Packagist, the standard `composer require hradigital/php-exceptions-laravel` will apply.

## Registration

The service provider is auto-discovered &mdash; no manual registration needed in most apps. If auto-discovery is disabled for this package, register it explicitly:

**Laravel 11 / 12** &mdash; `bootstrap/providers.php`:

```php
return [
    HraDigital\Components\ExceptionRenderer\ExceptionsServiceProvider::class,
];
```

**Laravel 10 (and below)** &mdash; `config/app.php`:

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    HraDigital\Components\ExceptionRenderer\ExceptionsServiceProvider::class,
])->toArray(),
```

## What the provider does

On `boot()`, the provider:

1. Binds `ExceptionRenderer` as a singleton in the container (so app code can resolve it and add custom strategies).
2. Hooks into Laravel's exception handler via `renderable()`, intercepting any thrown `AbstractBaseException`.

The hook **only renders** when the incoming request is treated as an API request:

| Signal                                                  | Detected via                                   |
| ------------------------------------------------------- | ---------------------------------------------- |
| `Accept` header negotiates JSON                         | `$request->expectsJson()` / `wantsJson()`      |
| Request body is JSON                                    | `$request->isJson()`                           |
| URL path matches `api/*` (or is exactly `api`)          | `$request->is('api/*')`                        |
| Matched route name starts with `api.`                   | `$request->route()?->getName()`                |

For any other request, the callback returns `null`, so Laravel's default handler keeps rendering its usual HTML / Whoops / Blade error pages.

## Response shapes

### Default

Any `AbstractBaseException` rendered via `DefaultRenderer`:

```json
{
    "message": "Resource not found.",
    "code": 404,
    "data": { "id": 42 }
}
```

`data` is omitted when the exception was raised without structured payload (`hasData() === false`).

### Input / validation failures

Exceptions implementing `HraDigital\Components\Exceptions\Client\Request\RequestFailureInterface` are rendered via `InputFailureRenderer`:

```json
{
    "message": "Invalid input.",
    "code": 422,
    "rules": { "email": ["required"] },
    "failed": [
        { "fieldName": "email", "message": "email is required" }
    ]
}
```

`rules` mirrors `getFailures()` (the field-keyed rule list). `failed` is the flattened, per-message list derived from `getFailedMessages()` &mdash; one entry per `{fieldName, message}` pair, preserving field order.

## Adding a custom renderer strategy

Resolve the singleton and prepend a strategy. The first strategy whose `supports()` returns `true` wins; the bundled `DefaultRenderer` is the always-matching fallback.

```php
use HraDigital\Components\ExceptionRenderer\ExceptionRenderer;
use HraDigital\Components\ExceptionRenderer\Renderers\ExceptionRendererInterface;
use HraDigital\Components\Exceptions\AbstractBaseException;
use Symfony\Component\HttpFoundation\JsonResponse;

final class TenantQuotaRenderer implements ExceptionRendererInterface
{
    public function supports(AbstractBaseException $exception): bool
    {
        return $exception instanceof \App\Exceptions\TenantQuotaExceeded;
    }

    public function renderAsJson(AbstractBaseException $exception): JsonResponse
    {
        return new JsonResponse([
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'tenant' => $exception->getData()['tenant'] ?? null,
        ], $exception->getCode());
    }
}

// In a service provider's boot():
app(ExceptionRenderer::class)->add(new TenantQuotaRenderer());
```

`add()` always prepends, so later registrations override earlier ones.

## Public API

| Class / interface                                                              | Purpose                                                                         |
| ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------- |
| `ExceptionRenderer`                                                            | Strategy dispatcher; `renderAsJson()`, `add()`, `getStrategies()`, factory.     |
| `Renderers\ExceptionRendererInterface`                                         | Contract every strategy implements (`supports()` + `renderAsJson()`).           |
| `Renderers\DefaultRenderer`                                                    | Always-matching fallback; emits the default response shape.                     |
| `Renderers\InputFailureRenderer`                                               | Matches `RequestFailureInterface`; emits validation-failure shape.              |
| `ExceptionsServiceProvider`                                                    | Singleton binding + Laravel `renderable()` hook with API-request gating.        |

## Local development

```bash
composer install
composer ci          # lint + phpcs + phpstan + phpunit
composer test        # phpunit only
composer cs          # PSR-12 check
composer cs:fix      # PSR-12 autofix
composer stan        # phpstan (level 6)
```

## Continuous Integration

`.github/workflows/ci.yml` runs on every push and PR to `master`. It executes `lint → phpcs → phpstan → phpunit` against the supported PHP × Laravel matrix:

|       | Laravel 10 | Laravel 11 | Laravel 12 |
| ----- | :--------: | :--------: | :--------: |
| 8.1   | &#10003;   |            |            |
| 8.2   | &#10003;   | &#10003;   | &#10003;   |
| 8.3   |            | &#10003;   | &#10003;   |
| 8.4   |            |            | &#10003;   |

The workflow pins `illuminate/*` and `orchestra/testbench` per matrix cell with `composer require --no-update` before installing. Because the repo is private, `secrets.GITHUB_TOKEN` is wired into `composer config github-oauth.github.com` so any future private peer dependency under the `HRADigital` org resolves cleanly during install.

## Testing

The `tests/` suite covers every class in the package:

- `ExceptionRendererTest` &mdash; factory wiring, fallback path, strategy routing, prepend ordering, custom-constructor wiring.
- `Renderers/DefaultRendererTest` &mdash; status/message/code mapping, conditional `data` key.
- `Renderers/InputFailureRendererTest` &mdash; flattening of `failed`, `rules` mirroring, empty-payload behaviour.
- `ExceptionsServiceProviderTest` &mdash; Testbench-based: singleton binding, end-to-end HTTP rendering for JSON / `api/*` requests, and direct callback invocation proving non-API requests pass through (`null` return).
- `Support/Stubs` &mdash; shared anonymous-class factories for a generic exception and a `RequestFailureInterface` exception, excluded from the test suite.

## License

GPL-3.0-or-later &mdash; see [LICENSE](LICENSE). This matches the upstream `hradigital/php-exceptions` license.
