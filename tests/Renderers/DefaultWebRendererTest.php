<?php

declare(strict_types=1);

namespace HraDigital\Components\ExceptionRenderer\Tests\Renderers;

use HraDigital\Components\Exceptions\Client\ConflictException;
use HraDigital\Components\Exceptions\Client\DeniedAccessException;
use HraDigital\Components\Exceptions\Client\ForbiddenException;
use HraDigital\Components\Exceptions\Client\NotFoundException;
use HraDigital\Components\Exceptions\Client\TooManyRequestsException;
use HraDigital\Components\Exceptions\Client\UnprocessableEntityException;
use HraDigital\Components\Exceptions\Server\InternalServerErrorException;
use HraDigital\Components\ExceptionRenderer\Renderers\DefaultWebRenderer;
use HraDigital\Components\ExceptionRenderer\Tests\Support\Stubs;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class DefaultWebRendererTest extends TestCase
{
    public function testSupportsEveryPackageException(): void
    {
        $renderer = DefaultWebRenderer::createRenderer();

        $this->assertTrue($renderer->supports(new DeniedAccessException()));
        $this->assertTrue($renderer->supports(new NotFoundException()));
        $this->assertTrue($renderer->supports(new UnprocessableEntityException()));
        $this->assertTrue($renderer->supports(Stubs::genericException('generic', 0)));
    }

    /**
     * @return array<string, array{0: \HraDigital\Components\Exceptions\AbstractBaseException, 1: int}>
     */
    public static function statusProvider(): array
    {
        return [
            'denied access' => [new DeniedAccessException(), 401],
            'forbidden' => [new ForbiddenException(), 403],
            'not found' => [new NotFoundException(), 404],
            'conflict' => [new ConflictException(), 409],
            'unprocessable entity' => [new UnprocessableEntityException(), 422],
            'too many requests' => [new TooManyRequestsException(), 429],
            'internal server error' => [new InternalServerErrorException(), 500],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testRendersTheStatusTheExceptionStates(
        \HraDigital\Components\Exceptions\AbstractBaseException $exception,
        int $expected
    ): void {
        $renderer = DefaultWebRenderer::createRenderer();

        $response = $renderer->renderAsRedirect($exception, Request::create('/widgets'));

        $this->assertSame($expected, $response->getStatusCode());
    }

    public function testFallsBackToServerErrorWhenTheCodeIsNotAnErrorStatus(): void
    {
        $renderer = DefaultWebRenderer::createRenderer();
        $request = Request::create('/widgets');

        $this->assertSame(500, $renderer->renderAsRedirect(Stubs::genericException('x', 0), $request)->getStatusCode());
        $this->assertSame(500, $renderer->renderAsRedirect(Stubs::genericException('x', 200), $request)->getStatusCode());
        $this->assertSame(500, $renderer->renderAsRedirect(Stubs::genericException('x', 399), $request)->getStatusCode());
        $this->assertSame(500, $renderer->renderAsRedirect(Stubs::genericException('x', 600), $request)->getStatusCode());
    }

    public function testCarriesTheExceptionMessageWhenNoErrorViewExists(): void
    {
        $renderer = DefaultWebRenderer::createRenderer();

        $response = $renderer->renderAsRedirect(new ForbiddenException('not for you'), Request::create('/widgets'));

        $this->assertSame('not for you', $response->getContent());
    }
}
