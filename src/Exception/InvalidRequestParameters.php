<?php

declare(strict_types=1);

namespace GSteel\Akismet\Exception;

use RuntimeException;

final class InvalidRequestParameters extends RuntimeException implements GenericException
{
    public static function withMessage(string $message): self
    {
        return new self($message);
    }
}
