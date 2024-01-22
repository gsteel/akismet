<?php

declare(strict_types=1);

namespace GSteel\Akismet;

use GSteel\Akismet\Exception\ApiError;
use GSteel\Akismet\Exception\HttpError;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function http_build_query;
use function sprintf;
use function strtolower;

final class Client implements AkismetClient
{
    public function __construct(
        private string $apiKey,
        private string $websiteUri,
        private HttpClient $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function verifyKey(string|null $apiKey = null, string|null $websiteUri = null): bool
    {
        $request = $this->createRequest(self::VERIFY_KEY_URI)
            ->withBody($this->streamFactory->createStream(http_build_query([
                'key' => $apiKey ?? $this->apiKey,
                'blog' => $websiteUri ?? $this->websiteUri,
            ])));

        return strtolower((string) $this->sendRequest($request)->getBody()) === 'valid';
    }

    public function check(CommentParameters $parameters): Result
    {
        $parameters = $this->prepareParameters($parameters);

        $request = $this->apiAction($parameters, self::CHECK_ACTION);
        $response = $this->sendRequest($request);

        $body = (string) $response->getBody();
        $isInvalid = strtolower($body) !== 'true' && strtolower($body) !== 'false';

        if ($isInvalid) {
            throw ApiError::with($request, $response);
        }

        $isSpam = strtolower($body) === 'true';

        return new Result($parameters, $isSpam);
    }

    public function submitSpam(CommentParameters $parameters): void
    {
        $parameters = $this->prepareParameters($parameters);
        $request = $this->apiAction($parameters, self::SUBMIT_SPAM_ACTION);
        $response = $this->sendRequest($request);
        $this->assertSubmissionBodyIsExpectedValue($request, $response);
    }

    public function submitHam(CommentParameters $parameters): void
    {
        $parameters = $this->prepareParameters($parameters);
        $request = $this->apiAction($parameters, self::SUBMIT_HAM_ACTION);
        $response = $this->sendRequest($request);
        $this->assertSubmissionBodyIsExpectedValue($request, $response);
    }

    private function assertSubmissionBodyIsExpectedValue(RequestInterface $request, ResponseInterface $response): void
    {
        $expect = strtolower('Thanks for making the web a better place.');
        $body = strtolower((string) $response->getBody());
        if ($expect !== $body) {
            throw ApiError::with($request, $response);
        }
    }

    private function apiAction(CommentParameters $parameters, string $action): RequestInterface
    {
        return $this->createRequest($this->action($action))
            ->withBody($this->streamFactory->createStream(http_build_query($parameters->toParameterList())));
    }

    private function createRequest(string $url): RequestInterface
    {
        return $this->requestFactory->createRequest('POST', $url)
            ->withHeader('User-Agent', self::USER_AGENT)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
    }

    private function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $clientException) {
            throw HttpError::withFailedRequest($request, $clientException);
        }
    }

    private function action(string $name): string
    {
        return sprintf(self::API_URI_TEMPLATE, $this->apiKey, $name);
    }

    private function prepareParameters(CommentParameters $parameters): CommentParameters
    {
        if ($parameters->websiteUrl() === null) {
            return $parameters->withWebsiteUrl($this->websiteUri);
        }

        return $parameters;
    }
}
