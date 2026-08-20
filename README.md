# php-exceptions-laravel

[![Latest Stable Version](https://poser.pugx.org/hradigital/php-exceptions-laravel/v/stable)](https://packagist.org/packages/hradigital/php-exceptions-laravel)
[![Total Downloads](https://poser.pugx.org/hradigital/php-exceptions-laravel/downloads)](https://packagist.org/packages/hradigital/php-exceptions-laravel)
[![PHP Version Require](https://poser.pugx.org/hradigital/php-exceptions-laravel/require/php)](https://packagist.org/packages/hradigital/php-exceptions-laravel)
[![License](https://poser.pugx.org/hradigital/php-exceptions-laravel/license)](https://github.com/HRADigital/php-exceptions-laravel/blob/master/LICENSE)
[![CI](https://github.com/HRADigital/php-exceptions-laravel/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/HRADigital/php-exceptions-laravel/actions/workflows/ci.yml)

Laravel wiring and JSON / web renderers for `hradigital/php-exceptions`.

The base library ships platform-agnostic domain exceptions - `AbstractBaseException` and the `Client/` and `Server/` trees, aligned with HTTP 4xx/5xx semantics. It knows nothing about a transport, leaving every application to decide again how a thrown domain exception becomes a response.

This package decides it once. API requests get a uniform JSON body; web requests throwing a 422 land back on the originating form with errors and old input. Everything else falls through to Laravel's own handler.

- **`ExceptionRenderer`** - the JSON strategy dispatcher, resolving an exception to the first strategy that supports it, with an always-matching fallback.
- **`WebRenderer`** - the web strategy dispatcher, returning a redirect for the exceptions it handles and `null` for everything else.
- **`ExceptionsServiceProvider`** - auto-discovered, binding both dispatchers as singletons and registering two `renderable()` hooks on Laravel's exception handler.
- **API detection** - one shared rule across both hooks: the `Accept` header, a JSON body, an `api/*` path, or an `api.*` route name.
- **Bundled strategies** - `DefaultRenderer` and `InputFailureRenderer` for JSON, `InputFailureWebRenderer` and `UnprocessableEntityWebRenderer` for redirects.
- **Custom strategies** - resolve the singleton and prepend your own; the last registration wins.

## Requirements

| Package          | `hradigital/php-exceptions-laravel`                      |
| ---------------- | -------------------------------------------------------- |
| Namespace        | `HraDigital\Components\ExceptionRenderer`                |
| Requires         | PHP `^8.2`, `hradigital/php-exceptions` `^1.0`           |
| Laravel          | `^12.0`                                                  |
| License          | MPL-2.0                                                  |

## Installation

```bash
composer require hradigital/php-exceptions-laravel
```

## Registration

The service provider is auto-discovered &mdash; no manual registration needed in most apps. If auto-discovery is disabled for this package, register it explicitly:

`bootstrap/providers.php`:

```php
return [
    HraDigital\Components\ExceptionRenderer\ExceptionsServiceProvider::class,
];
```

## What the provider does

On `boot()`, the provider:

1. Binds `ExceptionRenderer` and `WebRenderer` as singletons in the container (so app code can resolve them and add custom strategies).
2. Registers two `renderable()` hooks on Laravel's exception handler — one for API requests (returns `JsonResponse`), one for web requests (returns `RedirectResponse`).

API detection is shared between both hooks:

| Signal                                                  | Detected via                                   |
| ------------------------------------------------------- | ---------------------------------------------- |
| `Accept` header negotiates JSON                         | `$request->expectsJson()` / `wantsJson()`      |
| Request body is JSON                                    | `$request->isJson()`                           |
| URL path matches `api/*` (or is exactly `api`)          | `$request->is('api/*')`                        |
| Matched route name starts with `api.`                   | `$request->route()?->getName()`                |

- The **JSON hook** runs only for API requests; for web requests it returns `null`.
- The **web hook** runs only for non-API requests, and only when one of its strategies (`InputFailureWebRenderer`, `UnprocessableEntityWebRenderer`) supports the exception — i.e. for `UnprocessableEntityException` or its `RequestFailureException` specialisation. Anything else returns `null`, so Laravel's default handler keeps rendering its usual HTML / Whoops / Blade error pages.

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

### Web (redirect-back) responses

For non-API requests the `WebRenderer` runs two strategies, in order:

| Strategy                          | Matches                                              | Response                                                                       |
| --------------------------------- | ---------------------------------------------------- | ------------------------------------------------------------------------------ |
| `InputFailureWebRenderer`         | `RequestFailureInterface` (structured field errors)  | `back()->withErrors($exception->getFailedMessages())->withInput($input)`       |
| `UnprocessableEntityWebRenderer`  | `UnprocessableEntityException` (and any subclass)    | `back()->with('error', $exception->getMessage())->withInput($input)`           |

This means:

- Forms throwing `RequestFailureException::withFailures([...], [...])` get per-field errors flashed under the default `MessageBag` &mdash; Blade `@error('field')` / `$errors->has('field')` work out of the box.
- Plain `UnprocessableEntityException('the field is invalid')` flashes a single `error` key &mdash; render it in the layout via `@if (session('error')) ... @endif`.
- Submitted input is always flashed via `withInput()` so `old('field')` works in the form.

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
| `ExceptionRenderer`                                                            | JSON strategy dispatcher; `renderAsJson()`, `add()`, `getStrategies()`, factory. |
| `Renderers\ExceptionRendererInterface`                                         | Contract every JSON strategy implements (`supports()` + `renderAsJson()`).      |
| `Renderers\DefaultRenderer`                                                    | Always-matching JSON fallback; emits the default response shape.                |
| `Renderers\InputFailureRenderer`                                               | Matches `RequestFailureInterface`; emits validation-failure JSON shape.         |
| `WebRenderer`                                                                  | Web strategy dispatcher; `renderAsRedirect()`, `add()`, `getStrategies()`, factory. Returns `null` when no strategy supports the exception. |
| `Renderers\WebRendererInterface`                                               | Contract every web strategy implements (`supports()` + `renderAsRedirect()`).   |
| `Renderers\InputFailureWebRenderer`                                            | Matches `RequestFailureInterface`; redirects back with `withErrors` + `withInput`. |
| `Renderers\UnprocessableEntityWebRenderer`                                     | Matches `UnprocessableEntityException`; redirects back with flash `error` + `withInput`. |
| `ExceptionsServiceProvider`                                                    | Singleton bindings + two Laravel `renderable()` hooks (JSON for API, redirect for web). |

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

|       | Laravel 12 |
| ----- | :--------: |
| 8.2   | &#10003;   |
| 8.3   | &#10003;   |
| 8.4   | &#10003;   |
| 8.5   | &#10003;   |

The workflow pins `illuminate/*` and `orchestra/testbench` per matrix cell with `composer require --no-update` before installing.

Laravel 10 and 11 were dropped: both branches are past security support, so every `laravel/framework` release in them carries open security advisories and Composer refuses to install them.

## Testing

The `tests/` suite covers every class in the package:

- `ExceptionRendererTest` &mdash; factory wiring, fallback path, strategy routing, prepend ordering, custom-constructor wiring.
- `Renderers/DefaultRendererTest` &mdash; status/message/code mapping, conditional `data` key.
- `Renderers/InputFailureRendererTest` &mdash; flattening of `failed`, `rules` mirroring, empty-payload behaviour.
- `WebRendererTest` &mdash; default strategy wiring, null-on-unsupported, prepend ordering for custom strategies.
- `Renderers/InputFailureWebRendererTest` &mdash; back-with-errors flash + old-input restoration.
- `Renderers/UnprocessableEntityWebRendererTest` &mdash; back-with-error flash, `withInput`, subclass support.
- `ExceptionsServiceProviderTest` &mdash; Testbench-based: singleton bindings, end-to-end JSON rendering for `api/*` requests, end-to-end redirect rendering for `web` routes throwing `UnprocessableEntityException`, and direct callback invocation proving each hook returns `null` outside its scope.
- `Support/Stubs` &mdash; shared anonymous-class factories for a generic exception and a `RequestFailureInterface` exception, excluded from the test suite.

## License

Mozilla Public License 2.0 - see [LICENSE](LICENSE). This matches the upstream
`hradigital/php-exceptions` license.

You may use this package in closed-source and commercial products. If you modify and
distribute the package's own files, those files must remain under the MPL-2.0.

The `HRADigital` name and package names are not covered by that licence - see
[TRADEMARK.md](TRADEMARK.md).
