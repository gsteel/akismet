<?php

declare(strict_types=1);

namespace GSteel\Akismet;

use GSteel\Akismet\Exception\InvalidRequestParameters;
use Webmozart\Assert\Assert as WebmozartAssert;

use function filter_var;

use function sprintf;

use const FILTER_VALIDATE_URL;

final class Assert extends WebmozartAssert
{
    /**
     * @throws InvalidRequestParameters
     *
     * @psalm-pure
     * @inheritDoc
     */
    protected static function reportInvalidArgument($message): void
    {
        throw new InvalidRequestParameters($message);
    }

    public static function url(string $value, string $message = ''): void
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === $value) {
            return;
        }

        self::reportInvalidArgument(sprintf(
            $message ?: 'Expected a value to be a valid URL. Got: %s',
            self::valueToString($value),
        ));
    }

    public static function nullOrUrl(string|null $value, string $message = ''): void
    {
        self::__callStatic('nullOrUrl', [$value, $message]);
    }
}
