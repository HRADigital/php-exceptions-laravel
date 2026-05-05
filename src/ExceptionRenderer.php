<?php

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer;

use HraDigital\Components\ExceptionRenderer\Renderers\DefaultRenderer;
use HraDigital\Components\ExceptionRenderer\Renderers\ExceptionRendererInterface;
use HraDigital\Components\ExceptionRenderer\Renderers\InputFailureRenderer;
use HraDigital\Components\Exceptions\AbstractBaseException;
use Symfony\Component\HttpFoundation\JsonResponse;

use function array_merge;

/**
 * Renders any AbstractBaseException as a uniform JSON Response, picking the first
 * matching strategy or falling back to DefaultRenderer.
 */
class ExceptionRenderer
{
    public static function createRenderer(): self
    {
        return new self(
            DefaultRenderer::createRenderer(),
            InputFailureRenderer::createRenderer()
        );
    }

    /** @var ExceptionRendererInterface[] */
    private array $strategies = [];
    private DefaultRenderer $defaultRenderer;

    public function __construct(
        DefaultRenderer $defaultRenderer,
        InputFailureRenderer $inputFailureRenderer
    ) {
        $this->strategies = [
            $inputFailureRenderer,
        ];

        $this->defaultRenderer = $defaultRenderer;
    }

    public function add(ExceptionRendererInterface $renderer): void
    {
        $this->strategies = array_merge([$renderer], $this->strategies);
    }

    public function renderAsJson(AbstractBaseException $exception): JsonResponse
    {
        foreach ($this->strategies as $renderStrategy) {
            if ($renderStrategy->supports($exception)) {
                return $renderStrategy->renderAsJson($exception);
            }
        }

        return $this->defaultRenderer->renderAsJson($exception);
    }

    /**
     * @return ExceptionRendererInterface[]
     */
    public function getStrategies(): array
    {
        return $this->strategies;
    }

    public function getDefaultRenderer(): DefaultRenderer
    {
        return $this->defaultRenderer;
    }
}
