<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 *
 * Copyright (c) HRADigital - Hugo Rafael Azevedo.
 */

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer;

use HraDigital\Components\ExceptionRenderer\Renderers\DefaultWebRenderer;
use HraDigital\Components\ExceptionRenderer\Renderers\InputFailureWebRenderer;
use HraDigital\Components\ExceptionRenderer\Renderers\UnprocessableEntityWebRenderer;
use HraDigital\Components\ExceptionRenderer\Renderers\WebRendererInterface;
use HraDigital\Components\Exceptions\AbstractBaseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_merge;

/**
 * Renders any AbstractBaseException as a Laravel web response for non-API requests,
 * picking the first matching strategy or falling back to DefaultWebRenderer.
 *
 * Every Package Exception is answered here. An Exception that does not extend
 * AbstractBaseException never reaches this class, and keeps rendering through the
 * framework's own handler.
 */
class WebRenderer
{
    public static function createRenderer(): self
    {
        return new self(
            DefaultWebRenderer::createRenderer(),
            InputFailureWebRenderer::createRenderer(),
            UnprocessableEntityWebRenderer::createRenderer(),
        );
    }

    /** @var WebRendererInterface[] */
    private array $strategies = [];
    private DefaultWebRenderer $defaultRenderer;

    public function __construct(
        DefaultWebRenderer $defaultRenderer,
        InputFailureWebRenderer $inputFailureRenderer,
        UnprocessableEntityWebRenderer $unprocessableEntityRenderer,
    ) {
        $this->strategies = [
            $inputFailureRenderer,
            $unprocessableEntityRenderer,
        ];

        $this->defaultRenderer = $defaultRenderer;
    }

    public function add(WebRendererInterface $renderer): void
    {
        $this->strategies = array_merge([$renderer], $this->strategies);
    }

    public function supports(AbstractBaseException $exception): bool
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($exception)) {
                return true;
            }
        }

        return $this->defaultRenderer->supports($exception);
    }

    public function renderAsRedirect(AbstractBaseException $exception, Request $request): Response
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($exception)) {
                return $strategy->renderAsRedirect($exception, $request);
            }
        }

        return $this->defaultRenderer->renderAsRedirect($exception, $request);
    }

    /**
     * @return WebRendererInterface[]
     */
    public function getStrategies(): array
    {
        return $this->strategies;
    }

    public function getDefaultRenderer(): DefaultWebRenderer
    {
        return $this->defaultRenderer;
    }
}
