<?php

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer\Tests;

use HraDigital\Components\Exceptions\AbstractBaseException;
use HraDigital\Components\ExceptionRenderer\ExceptionRenderer;
use HraDigital\Components\ExceptionRenderer\Renderers\DefaultRenderer;
use HraDigital\Components\ExceptionRenderer\Renderers\ExceptionRendererInterface;
use HraDigital\Components\ExceptionRenderer\Renderers\InputFailureRenderer;
use HraDigital\Components\ExceptionRenderer\Tests\Support\Stubs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

class ExceptionRendererTest extends TestCase
{
    public function testFactoryWiresDefaultStrategies(): void
    {
        $renderer = ExceptionRenderer::createRenderer();

        $this->assertInstanceOf(DefaultRenderer::class, $renderer->getDefaultRenderer());

        $strategies = $renderer->getStrategies();
        $this->assertCount(1, $strategies);
        $this->assertInstanceOf(InputFailureRenderer::class, $strategies[0]);
    }

    public function testFallsBackToDefaultRendererWhenNoStrategySupports(): void
    {
        $renderer = ExceptionRenderer::createRenderer();
        $response = $renderer->renderAsJson(Stubs::genericException('oops', 500));

        $this->assertSame(500, $response->getStatusCode());
        $payload = Stubs::decode($response->getContent());
        $this->assertSame('oops', $payload['message']);
    }

    public function testRoutesRequestFailureToInputFailureStrategy(): void
    {
        $renderer = ExceptionRenderer::createRenderer();
        $response = $renderer->renderAsJson(
            Stubs::failureException('bad', 422, ['x' => ['r']], ['x' => ['m']])
        );

        $payload = Stubs::decode($response->getContent());
        $this->assertArrayHasKey('failed', $payload);
        $this->assertArrayHasKey('rules', $payload);
    }

    public function testAddPrependsStrategyAndOverridesBuiltins(): void
    {
        $renderer = ExceptionRenderer::createRenderer();
        $renderer->add($this->markerStrategy(599, ['custom' => true]));

        $response = $renderer->renderAsJson(Stubs::genericException());
        $payload = Stubs::decode($response->getContent());

        $this->assertSame(599, $response->getStatusCode());
        $this->assertTrue($payload['custom']);
    }

    public function testAddPreservesPriorOrderForLowerPriorityStrategies(): void
    {
        $renderer = ExceptionRenderer::createRenderer();
        $first = $this->markerStrategy(599, ['rank' => 'first']);
        $second = $this->markerStrategy(598, ['rank' => 'second']);

        $renderer->add($first);
        $renderer->add($second);

        $strategies = $renderer->getStrategies();
        $this->assertSame($second, $strategies[0]);
        $this->assertSame($first, $strategies[1]);
        $this->assertInstanceOf(InputFailureRenderer::class, $strategies[2]);
    }

    public function testCustomConstructorWiresStrategies(): void
    {
        $default = new DefaultRenderer();
        $input = new InputFailureRenderer();

        $renderer = new ExceptionRenderer($default, $input);

        $this->assertSame($default, $renderer->getDefaultRenderer());
        $this->assertSame([$input], $renderer->getStrategies());
    }

    private function markerStrategy(int $status, array $payload): ExceptionRendererInterface
    {
        return new class ($status, $payload) implements ExceptionRendererInterface {
            public function __construct(private int $status, private array $payload)
            {
            }

            public function supports(AbstractBaseException $exception): bool
            {
                return true;
            }

            public function renderAsJson(AbstractBaseException $exception): JsonResponse
            {
                return new JsonResponse($this->payload, $this->status);
            }
        };
    }
}
