<?php

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer\Tests\Renderers;

use HraDigital\Components\Exceptions\Client\UnprocessableEntityException;
use HraDigital\Components\ExceptionRenderer\Renderers\UnprocessableEntityWebRenderer;
use HraDigital\Components\ExceptionRenderer\Renderers\WebRendererInterface;
use HraDigital\Components\ExceptionRenderer\Tests\Support\Stubs;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;

class UnprocessableEntityWebRendererTest extends TestCase
{
    public function testFactoryReturnsInstance(): void
    {
        $this->assertInstanceOf(
            UnprocessableEntityWebRenderer::class,
            UnprocessableEntityWebRenderer::createRenderer()
        );
    }

    public function testImplementsRendererContract(): void
    {
        $this->assertInstanceOf(WebRendererInterface::class, new UnprocessableEntityWebRenderer());
    }

    public function testSupportsUnprocessableEntityExceptionsOnly(): void
    {
        $renderer = new UnprocessableEntityWebRenderer();

        $this->assertTrue($renderer->supports(new UnprocessableEntityException('boom')));
        $this->assertFalse($renderer->supports(Stubs::genericException()));
    }

    public function testSupportsSubclassesOfUnprocessableEntityException(): void
    {
        $renderer = new UnprocessableEntityWebRenderer();
        $subclass = new class ('boom') extends UnprocessableEntityException {
        };

        $this->assertTrue($renderer->supports($subclass));
    }

    public function testRendersBackWithErrorFlashAndInput(): void
    {
        $exception = new UnprocessableEntityException('the field is invalid');
        $request = Request::create('/widgets/1', 'PUT', ['label' => 'changed']);
        $request->headers->set('referer', 'http://localhost/widgets/1/edit');
        $this->app->instance('request', $request);

        $response = (new UnprocessableEntityWebRenderer())->renderAsRedirect($exception, $request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('http://localhost/widgets/1/edit', $response->getTargetUrl());

        $session = $response->getSession();
        $this->assertNotNull($session);
        $this->assertSame('the field is invalid', $session->get('error'));
        $this->assertSame(['label' => 'changed'], $session->getOldInput());
    }
}
