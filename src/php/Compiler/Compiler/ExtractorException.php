<?php

declare(strict_types=1);

namespace Phel\Compiler\Compiler;

use RuntimeException;

final class ExtractorException extends RuntimeException
{
    public static function cannotReadFile(string $path): self
    {
        return new self('Cannot read file: ' . $path);
    }

    public static function cannotExtractNamespaceFromPath(string $path): self
    {
        return new self('Cannot extract namespace from file: ' . $path);
    }

    public static function cannotParseFile(string $path): self
    {
        return new self('Cannot parse file: ' . $path);
    }
}
