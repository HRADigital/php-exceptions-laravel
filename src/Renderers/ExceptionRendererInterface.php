<?php

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer\Renderers;

use HraDigital\Components\Exceptions\AbstractBaseException;
use Symfony\Component\HttpFoundation\JsonResponse;

interface ExceptionRendererInterface
{
    /**
     * Returns TRUE if Renderer can Render Exception.
     */
    public function supports(AbstractBaseException $exception): bool;

    /**
     * Will prefill and render any AbstractBaseException as a uniform JsonResponse.
     */
    public function renderAsJson(AbstractBaseException $exception): JsonResponse;
}
