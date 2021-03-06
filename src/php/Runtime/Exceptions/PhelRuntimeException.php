<?php

declare(strict_types=1);

namespace Phel\Runtime\Exceptions;

use RuntimeException;

final class PhelRuntimeException extends RuntimeException
{
    public static function couldNotBeLoadedFrom(string $runtimePath): self
    {
        return new self('The Runtime could not be loaded from: ' . $runtimePath);
    }
}
