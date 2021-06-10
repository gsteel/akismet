<?php

declare(strict_types=1);

namespace GSteel\Akismet\Exception;

use InvalidArgumentException;

final class InvalidArgument extends InvalidArgumentException implements GenericException
{
}
