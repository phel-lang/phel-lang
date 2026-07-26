<?php

declare(strict_types=1);

namespace Phel\Lint\Domain\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * Raised when a collected `.phel` file, or a directory that should have been
 * walked for them, cannot be read. Skipping it instead would report the input
 * as clean and let `phel lint` exit 0, so a permission problem or a path
 * removed mid-run would look like a passing lint.
 *
 * @internal
 */
final class LintSourceException extends RuntimeException
{
    public static function cannotRead(string $path): self
    {
        return new self(sprintf('Cannot read file to lint: %s', $path));
    }

    public static function cannotWalkDirectory(string $path, Throwable $previous): self
    {
        return new self(
            sprintf('Cannot read directory to lint: %s', $path),
            0,
            $previous,
        );
    }
}
