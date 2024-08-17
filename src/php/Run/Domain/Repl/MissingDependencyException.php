<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use RuntimeException;

use function sprintf;

final class MissingDependencyException extends RuntimeException
{
    public static function missingExtension(string $extensionName): self
    {
        return new self(sprintf('Missing PHP extension: %s', $extensionName));
    }
}
