<?php

declare(strict_types=1);

namespace Phel\Command\Test\Exceptions;

use RuntimeException;

final class CannotFindAnyTestsException extends RuntimeException
{
    public static function inPaths(array $paths): self
    {
        return new self('Cannot find any tests in : ' . implode(',', $paths));
    }
}
