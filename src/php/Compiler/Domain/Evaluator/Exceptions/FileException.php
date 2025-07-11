<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator\Exceptions;

use RuntimeException;

final class FileException extends RuntimeException
{
    public static function canNotCreateTempFile(): self
    {
        return new self('Cannot create temp file.');
    }

    public static function canNotCreateFile(string $filename): self
    {
        return new self('Cannot require file: ' . $filename);
    }

    public static function canNotCreateDirectory(string $directory): self
    {
        return new self('Cannot create directory: ' . $directory);
    }
}
