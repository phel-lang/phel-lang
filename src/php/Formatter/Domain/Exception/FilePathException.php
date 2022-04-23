<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Exception;

use RuntimeException;

final class FilePathException extends RuntimeException
{
    public static function directoryFound(string $filename): self
    {
        return new self("{$filename}' is a directory but needs to be a file path");
    }

    public static function notFound(string $filename): self
    {
        return new self("File path '{$filename}' not found");
    }
}
