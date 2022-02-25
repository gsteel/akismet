<?php

declare(strict_types=1);

namespace GSteel\Akismet\Test\Exception;

use GSteel\Akismet\Exception\ApiError;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;

class ApiErrorTest extends TestCase
{
    public function testThatTheRequestAndResponseAreEqualToThoseReceived(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/foo');
        $response = new TextResponse('Foo');

        $error = ApiError::with($request, $response);

        self::assertSame($request, $error->getRequest());
        self::assertSame($response, $error->getResponse());
    }

    /**
     * @return array<array-key, array{0: string}>
     */
    public function headerNameProvider(): array
    {
        return [
            ['X-akismet-alert-msg'],
            ['X-akismet-debug-help'],
        ];
    }

    /** @dataProvider headerNameProvider */
    public function testThatCustomAkismetHeaderValuesWillBePResentInTheErrorMessageIfSet(string $headerName): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/foo');
        $response = (new TextResponse('Foo'))
            ->withHeader($headerName, 'Goats');

        $error = ApiError::with($request, $response);

        self::assertStringContainsString('Goats', $error->getMessage());
    }
}
