<?php

declare(strict_types=1);

namespace Phel\Command\Shared\Exceptions;

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

    public static function cannotReadComposerJsonInPath(string $path): self
    {
        return new self('Cannot read composer.json in: ' . $path);
    }

    public static function noPhelConfigurationFoundInComposerJson(): self
    {
        return new self('No Phel configuration found in composer.json');
    }
}
