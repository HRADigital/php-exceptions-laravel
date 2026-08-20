<?php

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer\Tests;

use HraDigital\Components\Exceptions\AbstractBaseException;
use HraDigital\Components\Exceptions\Client\UnprocessableEntityException;
use HraDigital\Components\ExceptionRenderer\Renderers\InputFailureWebRenderer;
use HraDigital\Components\ExceptionRenderer\Renderers\UnprocessableEntityWebRenderer;
use HraDigital\Components\ExceptionRenderer\Renderers\WebRendererInterface;
use HraDigital\Components\ExceptionRenderer\WebRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;

class WebRendererTest extends TestCase
{
    public function testFactoryReturnsInstanceWithDefaultStrategies(): void
    {
        $renderer = WebRenderer::createRenderer();

        $strategies = $renderer->getStrategies();

        $this->assertCount(2, $strategies);
        $this->assertInstanceOf(InputFailureWebRenderer::class, $strategies[0]);
        $this->assertInstanceOf(UnprocessableEntityWebRenderer::class, $strategies[1]);
    }

    public function testReturnsNullWhenNoStrategySupportsException(): void
    {
        $renderer = WebRenderer::createRenderer();
        $exception = $this->genericException();
        $request = Request::create('/widgets');

        $this->assertFalse($renderer->supports($exception));
        $this->assertNull($renderer->renderAsRedirect($exception, $request));
    }

    public function testRoutesUnprocessableEntityToFallbackStrategy(): void
    {
        $renderer = WebRenderer::createRenderer();
        $exception = new UnprocessableEntityException('boom');
        $request = Request::create('/widgets', 'POST', ['x' => 1]);
        $request->headers->set('referer', 'http://localhost/widgets/new');
        $this->app->instance('request', $request);

        $this->assertTrue($renderer->supports($exception));
        $response = $renderer->renderAsRedirect($exception, $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('boom', $response->getSession()->get('error'));
    }

    public function testCustomStrategyTakesPrecedenceWhenAdded(): void
    {
        $renderer = WebRenderer::createRenderer();
        $custom = new class implements WebRendererInterface {
            public function supports(AbstractBaseException $exception): bool
            {
                return true;
            }

            public function renderAsRedirect(AbstractBaseException $exception, Request $request): RedirectResponse
            {
                return new RedirectResponse('http://override.test/done');
            }
        };

        $renderer->add($custom);

        $response = $renderer->renderAsRedirect(new UnprocessableEntityException('boom'), Request::create('/'));

        $this->assertNotNull($response);
        $this->assertSame('http://override.test/done', $response->getTargetUrl());
    }

    private function genericException(): AbstractBaseException
    {
        return new class ('generic', 500) extends AbstractBaseException {
            public function __construct(string $message, int $code)
            {
                $this->code = $code;
                parent::__construct($message);
            }
        };
    }
}
