<?php

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer\Tests;

use HraDigital\Components\Exceptions\AbstractBaseException;
use HraDigital\Components\ExceptionRenderer\ExceptionRenderer;
use HraDigital\Components\ExceptionRenderer\ExceptionsServiceProvider;
use HraDigital\Components\ExceptionRenderer\Tests\Support\Stubs;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

class ExceptionsServiceProviderTest extends TestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ExceptionsServiceProvider::class];
    }

    public function testRegistersExceptionRendererAsSingleton(): void
    {
        $a = $this->app->make(ExceptionRenderer::class);
        $b = $this->app->make(ExceptionRenderer::class);

        $this->assertInstanceOf(ExceptionRenderer::class, $a);
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

    public function testCallbackReturnsNullForNonApiRequest(): void
    {
        $callback = $this->capturedRenderable();

        $this->assertNotNull($callback, 'expected provider to register a renderable callback');

        $exception = Stubs::genericException('boom', 418);
        $request = Request::create('/widgets', 'GET');

        $result = $callback($exception, $request);

        $this->assertNull($result);
    }

    public function testCallbackRendersForJsonRequest(): void
    {
        $callback = $this->capturedRenderable();

        $exception = Stubs::genericException('boom', 418);
        $request = Request::create('/widgets', 'GET');
        $request->headers->set('Accept', 'application/json');

        $result = $callback($exception, $request);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(418, $result->getStatusCode());
    }

    private function capturedRenderable(): ?\Closure
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
            $params = (new \ReflectionFunction($callback))->getParameters();
            if ($params === []) {
                continue;
            }
            $type = $params[0]->getType();
            if ($type instanceof \ReflectionNamedType && $type->getName() === AbstractBaseException::class) {
                return $callback;
            }
        }

        return null;
    }
}
