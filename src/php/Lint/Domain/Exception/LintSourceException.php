<?php

declare(strict_types=1);

namespace Phel\Lint\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Raised when a collected `.phel` file cannot be read. Skipping it instead
 * would report the file as clean and let `phel lint` exit 0, so a permission
 * problem or a file deleted mid-run would look like a passing lint.
 */
final class LintSourceException extends RuntimeException
{
    public static function cannotRead(string $path): self
    {
        return new self(sprintf('Cannot read file to lint: %s', $path));
    }
}
