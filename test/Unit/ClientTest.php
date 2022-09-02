<?php

declare(strict_types=1);

namespace GSteel\Akismet\Test;

use GSteel\Akismet\Client;
use GSteel\Akismet\CommentParameters;
use GSteel\Akismet\CommentType;
use GSteel\Akismet\Exception\ApiError;
use GSteel\Akismet\Exception\HttpError;
use Http\Client\Exception\NetworkException;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function current;
use function file_get_contents;
use function urlencode;

class ClientTest extends TestCase
{
    /** @var \Http\Mock\Client */
    private $httpClient;
    /** @var Client */
    private $akismet;
    /** @var StreamFactory */
    private $streamFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = new \Http\Mock\Client(
            new ResponseFactory(),
        );
        $this->streamFactory = new StreamFactory();
        $this->akismet = new Client(
            'APIKEY',
            'https://www.example.com',
            $this->httpClient,
            new RequestFactory(),
            $this->streamFactory,
        );
    }

    private function fixture(string $fileName): string
    {
        return file_get_contents(__DIR__ . '/fixtures/' . $fileName);
    }

    private function responseFixture(string $fileName): ResponseInterface
    {
        return Response\Serializer::fromString($this->fixture($fileName));
    }

    private function responseWithBody(string $body): ResponseInterface
    {
        $body = $this->streamFactory->createStream($body);

        return (new Response())->withBody($body);
    }

    public function testThatVerifyingTheKeyIsSuccessfulWhenTheApiIndicatesItIsValid(): RequestInterface
    {
        $this->httpClient->setDefaultResponse($this->responseWithBody('valid'));
        self::assertTrue($this->akismet->verifyKey('WHATEVER', 'https://other.example.com'));

        return $this->httpClient->getLastRequest();
    }

    /** @depends testThatVerifyingTheKeyIsSuccessfulWhenTheApiIndicatesItIsValid */
    public function testThatTheKeyVerificationRequestUsesTheGivenUrl(RequestInterface $request): void
    {
        self::assertStringContainsString(urlencode('https://other.example.com'), (string) $request->getBody());
    }

    /** @depends testThatVerifyingTheKeyIsSuccessfulWhenTheApiIndicatesItIsValid */
    public function testThatTheKeyVerificationRequestUsesTheGivenApiKey(RequestInterface $request): void
    {
        self::assertStringContainsString(urlencode('WHATEVER'), (string) $request->getBody());
    }

    /** @depends testThatVerifyingTheKeyIsSuccessfulWhenTheApiIndicatesItIsValid */
    public function testTheRequestHasContentTypeHeader(RequestInterface $request): void
    {
        $header = $request->getHeader('Content-Type');
        self::assertCount(1, $header);
        $value = current($header);
        self::assertIsString($value);
        self::assertEquals('application/x-www-form-urlencoded', $value);
    }

    /** @depends testThatVerifyingTheKeyIsSuccessfulWhenTheApiIndicatesItIsValid */
    public function testTheRequestHasANonEmptyUserAgentHeader(RequestInterface $request): void
    {
        $header = $request->getHeader('User-Agent');
        self::assertCount(1, $header);
        $value = current($header);
        self::assertIsString($value);
        self::assertNotEmpty($value);
    }

    public function testThatKeyVerificationArgumentsDefaultToTheConfiguredValues(): void
    {
        $this->httpClient->setDefaultResponse($this->responseWithBody('valid'));
        $this->akismet->verifyKey();
        $request = $this->httpClient->getLastRequest();
        self::assertStringContainsString(urlencode('https://www.example.com'), (string) $request->getBody());
        self::assertStringContainsString(urlencode('APIKEY'), (string) $request->getBody());
    }

    public function testThatPsrExceptionsAreWrappedDuringKeyVerification(): void
    {
        $willThrow = new NetworkException('Bad News', new Request());
        $this->httpClient->setDefaultException($willThrow);
        try {
            $this->akismet->verifyKey();
            self::fail('An exception was not thrown');
        } catch (HttpError $error) {
            self::assertStringContainsString(urlencode('https://www.example.com'), (string) $error->getRequest()->getBody());
            self::assertStringContainsString('Bad News', $error->getMessage());

            return;
        }
    }

    public function testThatAnApiErrorIsThrownWhenTheResponseIndicatesTheParametersAreInvalid(): void
    {
        $this->httpClient->setDefaultResponse(
            $this->responseFixture('missing-ip.http'),
        );
        $params = (new CommentParameters())
            ->withRequestParams('127.0.0.1')
            ->withComment('Anything', CommentType::forumPost());

        $this->expectException(ApiError::class);
        $this->expectExceptionMessage('Empty "user_ip" value');
        $this->akismet->check($params);
    }

    public function testThatThrownApiErrorsContainReferencesToTheRequestAndResponse(): void
    {
        $response = $this->responseFixture('missing-ip.http');
        $this->httpClient->setDefaultResponse($response);

        $params = (new CommentParameters())
            ->withRequestParams('127.0.0.1')
            ->withComment('Anything', CommentType::forumPost());

        try {
            $this->akismet->check($params);

            $this->fail('An exception was no thrown');
        } catch (ApiError $error) {
            self::assertSame($response, $error->getResponse());
            self::assertInstanceOf(RequestInterface::class, $error->getRequest());
        }
    }

    public function testThatASpamResponseIsConsideredSpam(): void
    {
        $this->httpClient->setDefaultResponse(
            $this->responseFixture('spam-response.http'),
        );
        $params = (new CommentParameters())
            ->withRequestParams('127.0.0.1')
            ->withComment('Anything', CommentType::forumPost());
        self::assertTrue($this->akismet->check($params)->isSpam());
    }

    public function testThatAHamResponseIsConsideredHam(): void
    {
        $this->httpClient->setDefaultResponse(
            $this->responseFixture('ham-response.http'),
        );
        $params = (new CommentParameters())
            ->withRequestParams('127.0.0.1')
            ->withComment('Anything', CommentType::forumPost());
        self::assertFalse($this->akismet->check($params)->isSpam());
    }

    public function testSubmitSpamIsExceptionalForAnInvalidRequest(): void
    {
        $this->httpClient->setDefaultResponse(
            $this->responseFixture('missing-ip.http'),
        );
        $params = (new CommentParameters())
            ->withRequestParams('127.0.0.1')
            ->withComment('Anything', CommentType::forumPost());

        $this->expectException(ApiError::class);
        $this->expectExceptionMessage('Empty "user_ip" value');
        $this->akismet->submitSpam($params);
    }

    public function testSubmitHamIsExceptionalForAnInvalidRequest(): void
    {
        $this->httpClient->setDefaultResponse(
            $this->responseFixture('missing-ip.http'),
        );
        $params = (new CommentParameters())
            ->withRequestParams('127.0.0.1')
            ->withComment('Anything', CommentType::reply());

        $this->expectException(ApiError::class);
        $this->expectExceptionMessage('Empty "user_ip" value');
        $this->akismet->submitHam($params);
    }

    public function testThatTheConfiguredWebsiteAddressWillNotBeUsedWhenAHostNameIsSetInTheRequestParameters(): void
    {
        $this->httpClient->setDefaultResponse(
            $this->responseFixture('spam-response.http'),
        );
        $params = (new CommentParameters())
            ->withRequestParams('127.0.0.1')
            ->withComment('Anything', CommentType::signup())
            ->withHostInformation('https://temp.example.com');

        $result = $this->akismet->check($params);
        self::assertEquals('https://temp.example.com', $result->parameters()->websiteUrl());
    }

    public function testThatSubmittingSpamSuccessfullyDoesNotYieldAnyErrors(): void
    {
        $this->httpClient->setDefaultResponse(
            $this->responseFixture('submit-spam-response.http'),
        );
        $params = (new CommentParameters())
            ->withRequestParams('127.0.0.1')
            ->withComment('Anything', CommentType::message());

        $this->akismet->submitSpam($params);
        $this->addToAssertionCount(1);
    }

    public function testThatSubmittingHamSuccessfullyDoesNotYieldAnyErrors(): void
    {
        $this->httpClient->setDefaultResponse(
            $this->responseFixture('submit-spam-response.http'),
        );
        $params = (new CommentParameters())
            ->withRequestParams('127.0.0.1')
            ->withComment('Anything', CommentType::message());

        $this->akismet->submitHam($params);
        $this->addToAssertionCount(1);
    }
}
