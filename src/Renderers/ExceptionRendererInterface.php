<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 *
 * Copyright (c) HRADigital - Hugo Rafael Azevedo.
 */

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
