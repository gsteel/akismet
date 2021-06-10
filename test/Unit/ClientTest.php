<?php

declare(strict_types=1);

namespace GSteel\Akismet\Test;

use GSteel\Akismet\Client;
use GSteel\Akismet\Exception\HttpError;
use Http\Client\Exception\NetworkException;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

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

        $this->httpClient = new \Http\Mock\Client();
        $this->streamFactory = new StreamFactory();
        $this->akismet = new Client(
            'APIKEY',
            'https://www.example.com',
            $this->httpClient,
            new RequestFactory(),
            $this->streamFactory
        );
    }

    private function responseWithBody(string $body): ResponseInterface
    {
        $body = $this->streamFactory->createStream($body);

        return (new Response())->withBody($body);
    }

    public function testThatVerifyingTheKeyIsSuccessfulWhenTheApiIndicatesItIsValid(): RequestInterface
    {
        $this->httpClient->setDefaultResponse($this->responseWithBody('valid'));
        self::assertTrue($this->akismet->verifyKey('https://other.example.com', 'WHATEVER'));

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
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
