<?php

declare(strict_types=1);

namespace Phel\Exceptions;

use RuntimeException;

final class FileException extends RuntimeException
{
    public static function canNotCreateTempFile(): self
    {
        return new self('Can not create temp file.');
    }

    public static function canNotCreateFile(string $filename): self
    {
        return new self('Can not require file: ' . $filename);
    }
}
