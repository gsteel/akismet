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
    /** @var string */
    private $apiKey;
    /** @var HttpClient */
    private $httpClient;
    /** @var RequestFactoryInterface */
    private $requestFactory;
    /** @var StreamFactoryInterface */
    private $streamFactory;
    /** @var string */
    private $websiteUri;

    public function __construct(
        string $apiKey,
        string $websiteUri,
        HttpClient $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->apiKey = $apiKey;
        $this->websiteUri = $websiteUri;
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    public function verifyKey(?string $apiKey = null, ?string $websiteUri = null): bool
    {
        $request = $this->requestFactory->createRequest('POST', self::VERIFY_KEY_URI)
            ->withBody($this->streamFactory->createStream(http_build_query([
                'key' => $apiKey ?? $this->apiKey,
                'blog' => $websiteUri ?? $this->websiteUri,
            ])));

        return strtolower((string) $this->sendRequest($request)->getBody()) === 'valid';
    }

    public function check(CommentParameters $parameters): Result
    {
        $parameters = $this->prepareParameters($parameters);

        $response = $this->apiAction($parameters, self::CHECK_ACTION);
        $body = (string) $response->getBody();
        $isInvalid = strtolower($body) !== 'true' && strtolower($body) !== 'false';

        if ($isInvalid) {
            throw ApiError::fromResponse($response);
        }

        $isSpam = strtolower($body) === 'true';

        return new Result($parameters, $isSpam);
    }

    public function submitSpam(CommentParameters $parameters): void
    {
        $parameters = $this->prepareParameters($parameters);
        $response = $this->apiAction($parameters, self::SUBMIT_SPAM_ACTION);
        $this->assertSubmissionBodyIsExpectedValue($response);
    }

    public function submitHam(CommentParameters $parameters): void
    {
        $parameters = $this->prepareParameters($parameters);
        $response = $this->apiAction($parameters, self::SUBMIT_HAM_ACTION);
        $this->assertSubmissionBodyIsExpectedValue($response);
    }

    private function assertSubmissionBodyIsExpectedValue(RequestInterface $request, ResponseInterface $response): void
    {
        $expect = strtolower('Thanks for making the web a better place.');
        $body = strtolower((string) $response->getBody());
        if ($expect !== $body) {
            throw ApiError::with($request, $response);
        }
    }

    private function apiAction(CommentParameters $parameters, string $action): ResponseInterface
    {
        $request = $this->requestFactory->createRequest('POST', $this->action($action))
            ->withBody($this->streamFactory->createStream(http_build_query($parameters->toParameterList())));

        return $this->sendRequest($request);
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
        if (! $parameters->websiteUrl()) {
            return $parameters->withWebsiteUrl($this->websiteUri);
        }

        return $parameters;
    }
}
