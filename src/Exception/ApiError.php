<?php

declare(strict_types=1);

namespace GSteel\Akismet\Exception;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function array_filter;
use function implode;
use function is_numeric;

final class ApiError extends RuntimeException implements GenericException
{
    /** @var ResponseInterface */
    private $response;

    public static function fromResponse(ResponseInterface $response): self
    {
        $errorCode = $response->getHeaderLine('X-akismet-alert-code');
        $errorCode = is_numeric($errorCode) ? (int) $errorCode : 0;

        $errorMessage = array_filter([
            $response->getHeaderLine('X-akismet-alert-msg'),
            $response->getHeaderLine('X-akismet-debug-help'),
        ]);

        $errorMessage = implode(', ', $errorMessage);
        $errorMessage = empty($errorMessage) ? 'An unknown error occurred' : $errorMessage;

        $instance = new self($errorMessage, $errorCode);
        $instance->response = $response;

        return $instance;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
