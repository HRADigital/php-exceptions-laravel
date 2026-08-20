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
use Symfony\Component\HttpFoundation\JsonResponse;

use function assert;

class InputFailureRenderer implements ExceptionRendererInterface
{
    public static function createRenderer(): self
    {
        return new self();
    }

    public function supports(AbstractBaseException $exception): bool
    {
        return $exception instanceof RequestFailureInterface;
    }

    public function renderAsJson(AbstractBaseException $exception): JsonResponse
    {
        assert($exception instanceof RequestFailureInterface);

        $failed = [];
        foreach ($exception->getFailedMessages() as $fieldName => $errors) {
            foreach ($errors as $errorMessage) {
                $failed[] = [
                    'fieldName' => $fieldName,
                    'message' => $errorMessage,
                ];
            }
        }

        return new JsonResponse(
            [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'failed' => $failed,
                'rules' => $exception->getFailures(),
            ],
            $exception->getCode()
        );
    }
}
