<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

use RuntimeException;

use function sprintf;

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

    public static function cannotResolveRequiredNamespace(string $requiredNs, string $requiringNs): self
    {
        return new self(sprintf(
            "Cannot find namespace '%s' required by '%s'. "
            . 'Check the spelling, or that its source file exists on the configured src/test/vendor dirs.',
            $requiredNs,
            $requiringNs,
        ));
    }
}
