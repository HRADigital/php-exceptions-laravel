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
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface WebRendererInterface
{
    /**
     * Returns TRUE if Renderer can Render Exception as a web response.
     */
    public function supports(AbstractBaseException $exception): bool;

    /**
     * Will render the AbstractBaseException as a Laravel web response.
     *
     * A form-facing strategy returns a redirect, typically `back()->with(...)->withInput()`
     * so the user lands on the originating form with input + flash data restored. A strategy
     * answering a status the caller cannot correct returns a response carrying that status.
     */
    public function renderAsRedirect(AbstractBaseException $exception, Request $request): Response;
}
