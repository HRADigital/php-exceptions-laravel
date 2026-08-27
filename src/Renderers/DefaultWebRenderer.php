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

use function response;
use function view;

/**
 * Last-resort web strategy, supporting every AbstractBaseException.
 *
 * Without it a Package Exception that no other strategy claims falls through to
 * the framework's generic handler, which reads no HTTP status off a DomainException
 * and answers 500 - even when the Exception states 401, 403, 404 or 429.
 *
 * The status is the Exception's own code. An application error view named
 * `errors/{status}.blade.php` is used when it exists, so an application keeps its
 * own error pages.
 */
class DefaultWebRenderer implements WebRendererInterface
{
    /** An Exception stating no HTTP code, or one outside the error range, is a server fault. */
    private const FALLBACK_STATUS = 500;
    private const LOWEST_STATUS = 400;
    private const HIGHEST_STATUS = 599;

    public static function createRenderer(): self
    {
        return new self();
    }

    public function supports(AbstractBaseException $exception): bool
    {
        return true;
    }

    public function renderAsRedirect(AbstractBaseException $exception, Request $request): Response
    {
        $status = $this->statusFor($exception);
        $view = 'errors.' . $status;

        if (view()->exists($view)) {
            return response()->view($view, ['exception' => $exception], $status);
        }

        return response($exception->getMessage(), $status);
    }

    private function statusFor(AbstractBaseException $exception): int
    {
        $code = (int) $exception->getCode();

        if ($code < self::LOWEST_STATUS || $code > self::HIGHEST_STATUS) {
            return self::FALLBACK_STATUS;
        }

        return $code;
    }
}
