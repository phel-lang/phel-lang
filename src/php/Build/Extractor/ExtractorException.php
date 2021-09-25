<?php

declare(strict_types=1);

namespace Phel\Build\Extractor;

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

    public static function duplicateNamespace(string $namespace, string $firstFile, string $secondFile): self
    {
        return new self("Two files have the same namespace: '$namespace'\n1st) '$firstFile'\n2nd) '$secondFile'");
    }
}
