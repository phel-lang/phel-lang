<?php

declare(strict_types=1);

namespace Phel\Command\Run\Exceptions;

use RuntimeException;

final class CannotLoadNamespaceException extends RuntimeException
{
    public static function withName(string $ns): self
    {
        throw new self('Cannot load namespace: ' . $ns);
    }
}
