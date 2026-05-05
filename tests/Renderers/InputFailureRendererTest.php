<?php

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer\Tests\Renderers;

use HraDigital\Components\ExceptionRenderer\Renderers\ExceptionRendererInterface;
use HraDigital\Components\ExceptionRenderer\Renderers\InputFailureRenderer;
use HraDigital\Components\ExceptionRenderer\Tests\Support\Stubs;
use PHPUnit\Framework\TestCase;

class InputFailureRendererTest extends TestCase
{
    public function testFactoryReturnsInstance(): void
    {
        $this->assertInstanceOf(InputFailureRenderer::class, InputFailureRenderer::createRenderer());
    }

    public function testImplementsRendererContract(): void
    {
        $this->assertInstanceOf(ExceptionRendererInterface::class, new InputFailureRenderer());
    }

    public function testSupportsOnlyRequestFailureExceptions(): void
    {
        $renderer = new InputFailureRenderer();

        $this->assertTrue($renderer->supports(Stubs::failureException()));
        $this->assertFalse($renderer->supports(Stubs::genericException()));
    }

    public function testRendersFailedMessagesFlattened(): void
    {
        $exception = Stubs::failureException(
            'invalid input',
            422,
            ['email' => ['required'], 'name' => ['required']],
            ['email' => ['email is required'], 'name' => ['name is required', 'name too short']]
        );

        $response = (new InputFailureRenderer())->renderAsJson($exception);
        $payload = Stubs::decode($response->getContent());

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('invalid input', $payload['message']);
        $this->assertSame(422, $payload['code']);
        $this->assertSame(['email' => ['required'], 'name' => ['required']], $payload['rules']);
        $this->assertSame(
            [
                ['fieldName' => 'email', 'message' => 'email is required'],
                ['fieldName' => 'name', 'message' => 'name is required'],
                ['fieldName' => 'name', 'message' => 'name too short'],
            ],
            $payload['failed']
        );
    }

    public function testRendersEmptyFailedListWhenNoMessages(): void
    {
        $exception = Stubs::failureException('invalid', 422, [], []);
        $response = (new InputFailureRenderer())->renderAsJson($exception);
        $payload = Stubs::decode($response->getContent());

        $this->assertSame([], $payload['failed']);
        $this->assertSame([], $payload['rules']);
    }
}
