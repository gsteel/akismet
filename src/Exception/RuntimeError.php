<?php

declare(strict_types=1);

namespace GSteel\Akismet\Exception;

use Http\Discovery\Exception as DiscoveryFailure;
use RuntimeException;

final class RuntimeError extends RuntimeException implements GenericException
{
    public static function discoveryFailed(DiscoveryFailure $failure): self
    {
        return new self(
            'HTTP Client or message related factory discovery failed.',
            0,
            $failure,
        );
    }
}
