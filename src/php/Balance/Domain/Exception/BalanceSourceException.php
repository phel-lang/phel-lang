<?php

declare(strict_types=1);

namespace Phel\Balance\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * @internal
 */
final class BalanceSourceException extends RuntimeException
{
    public static function cannotRead(string $path): self
    {
        return new self('Cannot read file: ' . $path);
    }

    public static function cannotWrite(string $path): self
    {
        return new self('Cannot write file: ' . $path);
    }

    public static function cannotWalkDirectory(string $directory, Throwable $previous): self
    {
        return new self('Cannot walk directory: ' . $directory, 0, $previous);
    }
}
