<?php

declare(strict_types=1);

namespace GSteel\Akismet\Exception;

use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;

use function sprintf;

final class HttpError extends RuntimeException implements GenericException
{
    /** @var RequestInterface */
    private $request;

    public static function withFailedRequest(RequestInterface $request, Throwable $error): self
    {
        $instance = new self(sprintf(
            'The request to %s failed with the error: %s',
            $request->getRequestTarget(),
            $error->getMessage()
        ), 0, $error);
        $instance->request = $request;

        return $instance;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
