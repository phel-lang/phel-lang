<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions\Extractor\ReadModel;

/**
 * Normalized source-map data extracted from a compiled PHP file.
 *
 * The mappings string is the raw VLQ-encoded source map whose line N maps to
 * line N of the generated code, i.e. excluding any file prefix lines
 * (`<?php`, declare statements, metadata comments). `codeStartLine` is the
 * 1-based line in the compiled file where the generated code begins, so a
 * trace line translates via `traceLine - (codeStartLine - 1)`.
 */
final readonly class SourceMapInformation
{
    public function __construct(
        private string $filename,
        private string $mappings,
        private int $codeStartLine = 1,
    ) {}

    public static function none(): self
    {
        return new self('', '');
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function mappings(): string
    {
        return $this->mappings;
    }

    public function codeStartLine(): int
    {
        return $this->codeStartLine;
    }

    public function hasFilename(): bool
    {
        return $this->filename !== '';
    }

    public function hasMappings(): bool
    {
        return $this->mappings !== '';
    }
}
