# php-exceptions-laravel

[![CI](https://github.com/HRADigital/php-exceptions-laravel/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/HRADigital/php-exceptions-laravel/actions/workflows/ci.yml)
[![Release Workflow](https://github.com/HRADigital/php-exceptions-laravel/actions/workflows/release.yml/badge.svg?branch=master)](https://github.com/HRADigital/php-exceptions-laravel/actions/workflows/release.yml)
[![Release](https://img.shields.io/github/v/release/HRADigital/php-exceptions-laravel)](https://github.com/HRADigital/php-exceptions-laravel/releases)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/hradigital/php-exceptions-laravel)](https://packagist.org/packages/hradigital/php-exceptions-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/hradigital/php-exceptions-laravel)](https://packagist.org/packages/hradigital/php-exceptions-laravel)
[![PHP Version](https://img.shields.io/packagist/php-v/hradigital/php-exceptions-laravel)](https://packagist.org/packages/hradigital/php-exceptions-laravel)
[![License](https://img.shields.io/github/license/HRADigital/php-exceptions-laravel)](LICENSE)
[![Last Commit](https://img.shields.io/github/last-commit/HRADigital/php-exceptions-laravel)](https://github.com/HRADigital/php-exceptions-laravel/commits/master)
[![Open Issues](https://img.shields.io/github/issues/HRADigital/php-exceptions-laravel)](https://github.com/HRADigital/php-exceptions-laravel/issues)
[![Contributors](https://img.shields.io/github/contributors/HRADigital/php-exceptions-laravel)](https://github.com/HRADigital/php-exceptions-laravel/graphs/contributors)
[![Stars](https://img.shields.io/github/stars/HRADigital/php-exceptions-laravel)](https://github.com/HRADigital/php-exceptions-laravel/stargazers)
[![Code Size](https://img.shields.io/github/languages/code-size/HRADigital/php-exceptions-laravel)](https://github.com/HRADigital/php-exceptions-laravel)
[![Laravel](https://img.shields.io/badge/Laravel-%5E12.0%20%7C%7C%20%5E13.0-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen)](phpstan.neon.dist)
[![Code Style](https://img.shields.io/badge/code%20style-PSR--12-blue)](phpcs.xml.dist)
[![Conventional Commits](https://img.shields.io/badge/Conventional%20Commits-1.0.0-FE5196?logo=conventionalcommits&logoColor=white)](https://conventionalcommits.org)

Laravel wiring and JSON / web renderers for the `hradigital/php-exceptions` domain exception tree.

The base library ships platform-agnostic exceptions - `AbstractBaseException` and the `Client/` and `Server/` trees, aligned with HTTP 4xx/5xx semantics - and knows nothing about a transport, leaving every application to decide again how a thrown domain exception becomes a response.

This package decides it once. API requests get a uniform JSON body; web requests throwing a 422 land back on the originating form with errors and old input; every other package exception is answered on the web with the HTTP status it declares. An exception that does not extend `AbstractBaseException` falls through to Laravel's own handler, untouched.

- **`ExceptionRenderer`** - the JSON strategy dispatcher, resolving an exception to the first strategy that supports it, with an always-matching fallback.
- **`WebRenderer`** - the web strategy dispatcher, resolving an exception to the first strategy that supports it, with an always-matching fallback.
- **`ExceptionsServiceProvider`** - auto-discovered, binding both dispatchers as singletons and registering two `renderable()` hooks on Laravel's exception handler.
- **API detection** - one shared rule across both hooks: the `Accept` header, a JSON body, an `api/*` path, or an `api.*` route name.
- **Bundled strategies** - `DefaultRenderer` and `InputFailureRenderer` for JSON, `InputFailureWebRenderer`, `UnprocessableEntityWebRenderer` and `DefaultWebRenderer` for the web.
- **Custom strategies** - resolve the singleton and prepend your own; the last registration wins.

## Requirements

| Package          | `hradigital/php-exceptions-laravel`                      |
| ---------------- | -------------------------------------------------------- |
| Namespace        | `HraDigital\Components\ExceptionRenderer`                |
| Requires         | PHP `^8.2`, `hradigital/php-exceptions` `^1.0`           |
| Laravel          | `^12.0 \|\| ^13.0`                                        |
| License          | MPL-2.0                                                  |

Laravel 13 itself requires PHP `^8.3`, so PHP 8.2 is supported on the Laravel 12 line only.

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
2. Registers two `renderable()` hooks on Laravel's exception handler — one for API requests (returns `JsonResponse`), one for web requests (returns `Response`).

Both hooks are type-hinted `AbstractBaseException`, so Laravel dispatches them for every exception in this package and every application subclass of one, and for nothing else. No configuration, no publishing step and no application wiring is involved — the provider is auto-discovered through `extra.laravel.providers`.

API detection is shared between both hooks:

| Signal                                                  | Detected via                                   |
| ------------------------------------------------------- | ---------------------------------------------- |
| `Accept` header negotiates JSON                         | `$request->expectsJson()` / `wantsJson()`      |
| Request body is JSON                                    | `$request->isJson()`                           |
| URL path matches `api/*` (or is exactly `api`)          | `$request->is('api/*')`                        |
| Matched route name starts with `api.`                   | `$request->route()?->getName()`                |

- The **JSON hook** runs only for API requests; for web requests it returns `null`.
- The **web hook** runs only for non-API requests, and answers every `AbstractBaseException`. `UnprocessableEntityException` and its `RequestFailureException` specialisation redirect back to the form; everything else is rendered by `DefaultWebRenderer` with the status the exception declares. An exception from outside this package never reaches the hook, so Laravel's default handler keeps rendering its usual HTML / Whoops / Blade error pages for those.

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

### Web responses

For non-API requests the `WebRenderer` runs its strategies in order, then falls back:

| Strategy                          | Matches                                              | Response                                                                       |
| --------------------------------- | ---------------------------------------------------- | ------------------------------------------------------------------------------ |
| `InputFailureWebRenderer`         | `RequestFailureInterface` (structured field errors)  | `back()->withErrors($exception->getFailedMessages())->withInput($input)`       |
| `UnprocessableEntityWebRenderer`  | `UnprocessableEntityException` (and any subclass)    | `back()->with('error', $exception->getMessage())->withInput($input)`           |
| `DefaultWebRenderer` (fallback)   | every `AbstractBaseException`                        | a response carrying the exception's own status - `errors/{status}.blade.php` when the application defines it, otherwise the exception message |

The fallback is what keeps a 401, 403, 404, 409 or 429 from rendering as a 500. Laravel reads no HTTP status off a `DomainException`, so without it any exception no strategy claimed reached the framework's generic handler and lost its status. An exception declaring no code, or a code outside 400-599, is answered 500.

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
| `WebRenderer`                                                                  | Web strategy dispatcher; `renderAsRedirect()`, `add()`, `getStrategies()`, `getDefaultRenderer()`, factory. Always returns a response. |
| `Renderers\WebRendererInterface`                                               | Contract every web strategy implements (`supports()` + `renderAsRedirect()`).   |
| `Renderers\DefaultWebRenderer`                                                 | Always-matching fallback; renders the status the exception declares. |
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

A `Makefile` wraps the same tools with scoping and a silent-on-success mode &mdash; run `make help` for the full list:

```bash
make validate                # syntax + phpcs + phpstan, run concurrently
make validate-implementation # serial pre-merge pipeline, stops at the first failure
make test                    # phpunit
make lint                    # phpcs (report only)      -- `make cs` is an alias
make lint-fix                # phpcbf autofix
make analyse                 # phpstan (level 6)
make syntax                  # php -l over src
```

Any target accepts `QUIET=1`, which suppresses all output on success (the test target prints only its final summary) and prints everything on failure. `FILES="a.php b.php"` scopes the file-based gates, `FILTER=SomeTest` narrows the test run, and `EXEC="docker exec <name>"` runs the PHP tools inside a container instead of natively.

Note that the Makefile follows the machine-wide target naming, which differs from the Composer scripts: `make lint` is PHPCS, whereas `composer lint` is the `php -l` syntax pass (`make syntax`).

## Continuous Integration

`.github/workflows/ci.yml` runs on every push and PR to `master`. It executes `lint → phpcs → phpstan → phpunit` against the supported PHP × Laravel matrix:

|       | Laravel 12 | Laravel 13 |
| ----- | :--------: | :--------: |
| 8.2   | &#10003;   | &mdash;    |
| 8.3   | &#10003;   | &#10003;   |
| 8.4   | &#10003;   | &#10003;   |
| 8.5   | &#10003;   | &#10003;   |

Laravel 13 requires PHP `^8.3`, so it has no 8.2 cell. Each Laravel line is paired with its own Testbench major &mdash; Laravel 12 with `orchestra/testbench` `10.*`, Laravel 13 with `11.*`.

The workflow pins `illuminate/*` and `orchestra/testbench` per matrix cell with `composer require --no-update` before installing.

Laravel 10 and 11 were dropped: both branches are past security support, so every `laravel/framework` release in them carries open security advisories and Composer refuses to install them.

## Testing

The `tests/` suite covers every class in the package:

- `ExceptionRendererTest` &mdash; factory wiring, fallback path, strategy routing, prepend ordering, custom-constructor wiring.
- `Renderers/DefaultRendererTest` &mdash; status/message/code mapping, conditional `data` key.
- `Renderers/InputFailureRendererTest` &mdash; flattening of `failed`, `rules` mirroring, empty-payload behaviour.
- `WebRendererTest` &mdash; default strategy wiring, fallback to `DefaultWebRenderer`, prepend ordering for custom strategies.
- `Renderers/DefaultWebRendererTest` &mdash; the status per exception family, the 500 fallback for a non-error code, and the message body.
- `Renderers/InputFailureWebRendererTest` &mdash; back-with-errors flash + old-input restoration.
- `Renderers/UnprocessableEntityWebRendererTest` &mdash; back-with-error flash, `withInput`, subclass support.
- `ExceptionsServiceProviderTest` &mdash; Testbench-based: singleton bindings, end-to-end JSON rendering for `api/*` requests, end-to-end redirect rendering for `web` routes throwing `UnprocessableEntityException`, end-to-end status rendering for a `web` route throwing a 403, proof that an exception outside the package still renders through the framework, and direct callback invocation proving each hook returns `null` outside its scope.
- `Support/Stubs` &mdash; shared anonymous-class factories for a generic exception and a `RequestFailureInterface` exception, excluded from the test suite.

## License

Mozilla Public License 2.0 - see [LICENSE](LICENSE). This matches the upstream
`hradigital/php-exceptions` license.

You may use this package in closed-source and commercial products. If you modify and
distribute the package's own files, those files must remain under the MPL-2.0.

The `HRADigital` name and package names are not covered by that licence - see
[TRADEMARK.md](TRADEMARK.md).
