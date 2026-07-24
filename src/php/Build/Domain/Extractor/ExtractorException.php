<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

use RuntimeException;
use Throwable;

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

    /**
     * @param ?Throwable $previous the lexer/parser/reader failure that caused this;
     *                             its message is appended so the caller sees why
     */
    public static function cannotParseFile(string $path, ?Throwable $previous = null): self
    {
        if (!$previous instanceof Throwable) {
            return new self('Cannot parse file: ' . $path);
        }

        return new self(
            sprintf('Cannot parse file: %s: %s', $path, $previous->getMessage()),
            0,
            $previous,
        );
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
