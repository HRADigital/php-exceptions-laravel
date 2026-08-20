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
use HraDigital\Components\Exceptions\Client\Request\RequestFailureInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

use function assert;
use function back;

class InputFailureWebRenderer implements WebRendererInterface
{
    public static function createRenderer(): self
    {
        return new self();
    }

    public function supports(AbstractBaseException $exception): bool
    {
        return $exception instanceof RequestFailureInterface;
    }

    public function renderAsRedirect(AbstractBaseException $exception, Request $request): RedirectResponse
    {
        assert($exception instanceof RequestFailureInterface);

        return back()
            ->withErrors($exception->getFailedMessages())
            ->withInput($request->input());
    }
}
