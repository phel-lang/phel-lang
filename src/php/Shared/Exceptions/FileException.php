<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions;

use RuntimeException;

final class FileException extends RuntimeException
{
    public static function canNotCreateFile(string $filename): self
    {
        return new self('Cannot require file: ' . $filename);
    }

    public static function canNotCreateDirectory(string $directory): self
    {
        return new self('Cannot create directory: ' . $directory);
    }

    public static function directoryIsNotWritable(string $directory): self
    {
        return new self('Directory is not writable: ' . $directory);
    }

    public static function directoryCanBeReplacedByAnotherUser(string $directory): self
    {
        return new self('Directory can be replaced by another user: ' . $directory);
    }

    public static function directoryIsOwnedByAnotherUser(string $directory): self
    {
        return new self('Directory is owned by another user: ' . $directory);
    }
}
