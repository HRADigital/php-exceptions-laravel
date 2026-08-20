<?php

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer\Tests\Renderers;

use HraDigital\Components\ExceptionRenderer\Renderers\InputFailureWebRenderer;
use HraDigital\Components\ExceptionRenderer\Renderers\WebRendererInterface;
use HraDigital\Components\ExceptionRenderer\Tests\Support\Stubs;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;

class InputFailureWebRendererTest extends TestCase
{
    public function testFactoryReturnsInstance(): void
    {
        $this->assertInstanceOf(InputFailureWebRenderer::class, InputFailureWebRenderer::createRenderer());
    }

    public function testImplementsRendererContract(): void
    {
        $this->assertInstanceOf(WebRendererInterface::class, new InputFailureWebRenderer());
    }

    public function testSupportsOnlyRequestFailureExceptions(): void
    {
        $renderer = new InputFailureWebRenderer();

        $this->assertTrue($renderer->supports(Stubs::failureException()));
        $this->assertFalse($renderer->supports(Stubs::genericException()));
    }

    public function testRendersBackWithErrorsAndInput(): void
    {
        $exception = Stubs::failureException(
            'invalid input',
            422,
            ['email' => ['required']],
            ['email' => ['email is required']]
        );
        $request = Request::create('/widgets', 'POST', ['email' => 'bad@', 'name' => 'x']);
        $request->headers->set('referer', 'http://localhost/widgets/create');
        $this->app->instance('request', $request);

        $response = (new InputFailureWebRenderer())->renderAsRedirect($exception, $request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('http://localhost/widgets/create', $response->getTargetUrl());

        $session = $response->getSession();
        $this->assertNotNull($session);
        /** @var \Illuminate\Support\ViewErrorBag $errors */
        $errors = $session->get('errors');
        $this->assertSame(['email is required'], $errors->getBag('default')->get('email'));

        $this->assertSame(['email' => 'bad@', 'name' => 'x'], $session->getOldInput());
    }
}
