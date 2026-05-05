<?php

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer;

use HraDigital\Components\Exceptions\AbstractBaseException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\JsonResponse;

use function is_object;
use function is_string;
use function method_exists;
use function str_starts_with;

class ExceptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExceptionRenderer::class, static function (): ExceptionRenderer {
            return ExceptionRenderer::createRenderer();
        });
    }

    public function boot(): void
    {
        /** @var ExceptionHandler $handler */
        $handler = $this->app->make(ExceptionHandler::class);

        if (! method_exists($handler, 'renderable')) {
            return;
        }

        $renderer = $this->app->make(ExceptionRenderer::class);

        $handler->renderable(
            static function (AbstractBaseException $exception, Request $request) use ($renderer): ?JsonResponse {
                if (! self::isApiRequest($request)) {
                    return null;
                }

                return $renderer->renderAsJson($exception);
            }
        );
    }

    /**
     * Treat as API when the client expects JSON, sends JSON, or hits an api.* route / api/* path.
     */
    private static function isApiRequest(Request $request): bool
    {
        if ($request->expectsJson() || $request->wantsJson() || $request->isJson()) {
            return true;
        }

        if ($request->is('api/*') || $request->is('api')) {
            return true;
        }

        $route = $request->route();
        if (is_object($route) && method_exists($route, 'getName')) {
            $name = $route->getName();
            if (is_string($name) && str_starts_with($name, 'api.')) {
                return true;
            }
        }

        return false;
    }
}
