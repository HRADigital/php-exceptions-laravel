<?php

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer\Tests\Renderers;

use HraDigital\Components\Exceptions\AbstractBaseException;
use HraDigital\Components\Exceptions\Client\Request\RequestFailureInterface;
use HraDigital\Components\ExceptionRenderer\Renderers\DefaultRenderer;
use HraDigital\Components\ExceptionRenderer\Renderers\ExceptionRendererInterface;
use HraDigital\Components\ExceptionRenderer\Tests\Support\Stubs;
use PHPUnit\Framework\TestCase;

class DefaultRendererTest extends TestCase
{
    public function testFactoryReturnsInstance(): void
    {
        $this->assertInstanceOf(DefaultRenderer::class, DefaultRenderer::createRenderer());
    }

    public function testImplementsRendererContract(): void
    {
        $this->assertInstanceOf(ExceptionRendererInterface::class, new DefaultRenderer());
    }

    public function testSupportsAnyAbstractBaseException(): void
    {
        $renderer = new DefaultRenderer();

        $this->assertTrue($renderer->supports(Stubs::genericException()));
        $this->assertTrue($renderer->supports(Stubs::failureException()));
    }

    public function testRendersMessageCodeAndStatus(): void
    {
        $exception = Stubs::genericException('boom', 418);
        $response = (new DefaultRenderer())->renderAsJson($exception);

        $this->assertSame(418, $response->getStatusCode());
        $payload = Stubs::decode($response->getContent());
        $this->assertSame('boom', $payload['message']);
        $this->assertSame(418, $payload['code']);
    }

    public function testOmitsDataKeyWhenExceptionHasNoData(): void
    {
        $response = (new DefaultRenderer())->renderAsJson(Stubs::genericException());
        $payload = Stubs::decode($response->getContent());

        $this->assertArrayNotHasKey('data', $payload);
    }

    public function testIncludesDataKeyWhenExceptionHasData(): void
    {
        $exception = Stubs::genericException('boom', 500, ['hint' => 'check inputs']);
        $response = (new DefaultRenderer())->renderAsJson($exception);
        $payload = Stubs::decode($response->getContent());

        $this->assertSame(['hint' => 'check inputs'], $payload['data']);
    }
}
