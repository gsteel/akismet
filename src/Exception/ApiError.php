<?php

declare(strict_types=1);

namespace GSteel\Akismet\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

use function array_filter;
use function implode;
use function is_numeric;

final class ApiError extends RuntimeException implements GenericException
{
    public function __construct(
        private RequestInterface $request,
        private ResponseInterface $response,
        string $message,
        int $code = 0,
        Throwable|null $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function with(RequestInterface $request, ResponseInterface $response): self
    {
        $errorCode = $response->getHeaderLine('X-akismet-alert-code');
        $errorCode = is_numeric($errorCode) ? (int) $errorCode : 0;

        $errorMessage = array_filter([
            $response->getHeaderLine('X-akismet-alert-msg'),
            $response->getHeaderLine('X-akismet-debug-help'),
        ]);

        $errorMessage = implode(', ', $errorMessage);
        $errorMessage = empty($errorMessage) ? 'An unknown error occurred' : $errorMessage;

        return new self($request, $response, $errorMessage, $errorCode);
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
