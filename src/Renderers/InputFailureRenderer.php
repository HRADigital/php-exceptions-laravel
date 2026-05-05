<?php

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer\Renderers;

use HraDigital\Components\Exceptions\AbstractBaseException;
use HraDigital\Components\Exceptions\Client\Request\RequestFailureInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

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
        /** @var RequestFailureInterface $exception */
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
