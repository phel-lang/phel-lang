<?php

declare(strict_types=1);

namespace Phel\Lint\Domain\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * Raised when a lint config file exists but cannot be used. A missing config
 * file is not an error (callers get the defaults); a present but broken one is,
 * because silently linting with the defaults hides the user's intent.
 */
final class LintConfigException extends RuntimeException
{
    public static function cannotRead(string $path): self
    {
        return new self(sprintf('Cannot read lint config file: %s', $path));
    }

    public static function cannotParse(string $path, Throwable $previous): self
    {
        return new self(
            sprintf('Cannot parse lint config file %s: %s', $path, $previous->getMessage()),
            0,
            $previous,
        );
    }

    public static function notAMap(string $path): self
    {
        return new self(sprintf(
            'Lint config file %s must contain a single map, e.g. {:rules {:phel/unused-binding :off}}',
            $path,
        ));
    }
}
