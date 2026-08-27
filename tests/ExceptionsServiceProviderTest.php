<?php

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer\Tests;

use HraDigital\Components\Exceptions\AbstractBaseException;
use HraDigital\Components\Exceptions\Client\UnprocessableEntityException;
use HraDigital\Components\ExceptionRenderer\ExceptionRenderer;
use HraDigital\Components\ExceptionRenderer\ExceptionsServiceProvider;
use HraDigital\Components\ExceptionRenderer\Tests\Support\Stubs;
use HraDigital\Components\ExceptionRenderer\WebRenderer;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ExceptionsServiceProviderTest extends TestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ExceptionsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.cipher', 'AES-256-CBC');
    }

    public function testRegistersExceptionRendererAsSingleton(): void
    {
        $a = $this->app->make(ExceptionRenderer::class);
        $b = $this->app->make(ExceptionRenderer::class);

        $this->assertInstanceOf(ExceptionRenderer::class, $a);
        $this->assertSame($a, $b);
    }

    public function testRegistersWebRendererAsSingleton(): void
    {
        $a = $this->app->make(WebRenderer::class);
        $b = $this->app->make(WebRenderer::class);

        $this->assertInstanceOf(WebRenderer::class, $a);
        $this->assertSame($a, $b);
    }

    public function testRendersExceptionForJsonAcceptingRequest(): void
    {
        Route::get('/widgets', function (): void {
            throw Stubs::genericException('boom', 418, ['hint' => 'no']);
        });

        $response = $this->getJson('/widgets');

        $response->assertStatus(418);
        $response->assertJson([
            'message' => 'boom',
            'code' => 418,
            'data' => ['hint' => 'no'],
        ]);
    }

    public function testRendersRequestFailureExceptionWithFailuresPayload(): void
    {
        Route::post('/widgets', function (): void {
            throw Stubs::failureException(
                'invalid input',
                422,
                ['email' => ['required']],
                ['email' => ['email is required']]
            );
        });

        $response = $this->postJson('/widgets', []);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'invalid input',
            'code' => 422,
            'rules' => ['email' => ['required']],
            'failed' => [
                ['fieldName' => 'email', 'message' => 'email is required'],
            ],
        ]);
    }

    public function testRendersForRequestUnderApiPathPrefix(): void
    {
        Route::get('api/widgets', function (): void {
            throw Stubs::genericException('api boom', 400);
        });

        $response = $this->get('api/widgets');

        $response->assertStatus(400);
        $response->assertJson(['message' => 'api boom', 'code' => 400]);
    }

    public function testJsonCallbackReturnsNullForNonApiRequest(): void
    {
        $callback = $this->capturedRenderable(JsonResponse::class);

        $this->assertNotNull($callback, 'expected provider to register a JSON renderable callback');

        $exception = Stubs::genericException('boom', 418);
        $request = Request::create('/widgets', 'GET');

        $result = $callback($exception, $request);

        $this->assertNull($result);
    }

    public function testJsonCallbackRendersForJsonRequest(): void
    {
        $callback = $this->capturedRenderable(JsonResponse::class);

        $exception = Stubs::genericException('boom', 418);
        $request = Request::create('/widgets', 'GET');
        $request->headers->set('Accept', 'application/json');

        $result = $callback($exception, $request);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(418, $result->getStatusCode());
    }

    public function testWebCallbackReturnsNullForApiRequest(): void
    {
        $callback = $this->capturedRenderable(Response::class);

        $this->assertNotNull($callback, 'expected provider to register a web renderable callback');

        $exception = new UnprocessableEntityException('boom');
        $request = Request::create('/widgets', 'POST');
        $request->headers->set('Accept', 'application/json');

        $result = $callback($exception, $request);

        $this->assertNull($result);
    }

    public function testWebCallbackRendersStatusForExceptionsNoStrategyClaims(): void
    {
        $callback = $this->capturedRenderable(Response::class);

        $exception = Stubs::genericException('boom', 403);
        $request = Request::create('/widgets', 'GET');

        $result = $callback($exception, $request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(403, $result->getStatusCode());
    }

    public function testWebCallbackFallsBackToServerErrorWhenExceptionStatesNoHttpCode(): void
    {
        $callback = $this->capturedRenderable(Response::class);

        $exception = Stubs::genericException('boom', 0);
        $request = Request::create('/widgets', 'GET');

        $result = $callback($exception, $request);

        $this->assertSame(500, $result->getStatusCode());
    }

    public function testWebCallbackRedirectsBackForUnprocessableEntityOnHtmlRequest(): void
    {
        $callback = $this->capturedRenderable(Response::class);

        $exception = new UnprocessableEntityException('the field is invalid');
        $request = Request::create('/widgets', 'POST', ['name' => 'x']);
        $request->headers->set('referer', 'http://localhost/widgets/new');
        $this->app->instance('request', $request);

        $result = $callback($exception, $request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame('http://localhost/widgets/new', $result->getTargetUrl());
        $this->assertSame('the field is invalid', $result->getSession()->get('error'));
        $this->assertSame(['name' => 'x'], $result->getSession()->getOldInput());
    }

    public function testWebFlowRedirectsBackEndToEndFromWebRoute(): void
    {
        Route::middleware(['web'])->post('/widgets', function (): void {
            throw new UnprocessableEntityException('label is invalid');
        });

        $response = $this->withSession([])->post('/widgets', ['label' => 'oops'], [
            'referer' => 'http://localhost/widgets/new',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('http://localhost/widgets/new');
        $response->assertSessionHas('error', 'label is invalid');
        $response->assertSessionHasInput(['label' => 'oops']);
    }

    public function testWebFlowRendersDeclaredStatusEndToEndFromWebRoute(): void
    {
        Route::middleware(['web'])->get('/widgets', function (): void {
            throw Stubs::genericException('not for you', 403);
        });

        $response = $this->get('/widgets');

        $response->assertStatus(403);
    }

    public function testExceptionOutsideThePackageKeepsRenderingThroughTheFramework(): void
    {
        Route::middleware(['web'])->get('/widgets', function (): void {
            throw new \RuntimeException('unrelated');
        });

        $response = $this->get('/widgets');

        // The Package claims nothing here - the framework's own handler answers.
        $response->assertStatus(500);
    }

    /**
     * @param class-string $expectedReturn class hinted on the registered closure's return type
     */
    private function capturedRenderable(string $expectedReturn): ?\Closure
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $reflection = new \ReflectionObject($handler);
        if (! $reflection->hasProperty('renderCallbacks')) {
            $this->fail('Laravel exception handler missing renderCallbacks; cannot inspect renderable hook.');
        }

        $prop = $reflection->getProperty('renderCallbacks');
        $prop->setAccessible(true);

        /** @var array<int, \Closure> $callbacks */
        $callbacks = $prop->getValue($handler);
        foreach ($callbacks as $callback) {
            $reflectionFn = new \ReflectionFunction($callback);
            $params = $reflectionFn->getParameters();
            if ($params === []) {
                continue;
            }
            $type = $params[0]->getType();
            if (! ($type instanceof \ReflectionNamedType) || $type->getName() !== AbstractBaseException::class) {
                continue;
            }

            $returnType = $reflectionFn->getReturnType();
            if (! ($returnType instanceof \ReflectionNamedType)) {
                continue;
            }
            if ($returnType->getName() === $expectedReturn) {
                return $callback;
            }
        }

        return null;
    }
}
