<?php

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer\Tests\Support;

use HraDigital\Components\Exceptions\AbstractBaseException;
use HraDigital\Components\Exceptions\Client\Request\RequestFailureInterface;
use PHPUnit\Framework\Assert;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class Stubs
{
    public static function genericException(
        string $message = 'generic',
        int $code = 500,
        array $data = []
    ): AbstractBaseException {
        return new class ($message, $code, $data) extends AbstractBaseException {
            public function __construct(string $message, int $code, array $data)
            {
                $this->code = $code;
                parent::__construct($message, $data);
            }
        };
    }

    public static function failureException(
        string $message = 'invalid',
        int $code = 422,
        array $rules = ['field' => ['required']],
        array $messages = ['field' => ['field is required']]
    ): AbstractBaseException {
        return new class ($message, $code, $rules, $messages) extends AbstractBaseException implements RequestFailureInterface {
            public function __construct(
                string $message,
                int $code,
                private array $rules,
                private array $messages
            ) {
                $this->code = $code;
                parent::__construct($message);
            }

            public function getFailures(): array
            {
                return $this->rules;
            }

            public function getFailedMessages(): array
            {
                return $this->messages;
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string|false $body): array
    {
        Assert::assertIsString($body);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
