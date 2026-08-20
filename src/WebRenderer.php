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

use HraDigital\Components\ExceptionRenderer\Renderers\InputFailureWebRenderer;
use HraDigital\Components\ExceptionRenderer\Renderers\UnprocessableEntityWebRenderer;
use HraDigital\Components\ExceptionRenderer\Renderers\WebRendererInterface;
use HraDigital\Components\Exceptions\AbstractBaseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

use function array_merge;

/**
 * Renders any AbstractBaseException as a Laravel RedirectResponse for non-API (web) requests,
 * picking the first matching strategy or returning NULL when no strategy supports the exception
 * (so Laravel's default error page keeps rendering for unrelated cases like 404 / 500).
 */
class WebRenderer
{
    public static function createRenderer(): self
    {
        return new self(
            InputFailureWebRenderer::createRenderer(),
            UnprocessableEntityWebRenderer::createRenderer(),
        );
    }

    /** @var WebRendererInterface[] */
    private array $strategies = [];

    public function __construct(
        InputFailureWebRenderer $inputFailureRenderer,
        UnprocessableEntityWebRenderer $unprocessableEntityRenderer,
    ) {
        $this->strategies = [
            $inputFailureRenderer,
            $unprocessableEntityRenderer,
        ];
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

        return false;
    }

    public function renderAsRedirect(AbstractBaseException $exception, Request $request): ?RedirectResponse
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($exception)) {
                return $strategy->renderAsRedirect($exception, $request);
            }
        }

        return null;
    }

    /**
     * @return WebRendererInterface[]
     */
    public function getStrategies(): array
    {
        return $this->strategies;
    }
}
