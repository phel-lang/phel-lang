<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\ValueObject;

/**
 * Value Object representing the result of compiling Phel code.
 * Immutable container for compiled PHP code and optional source map.
 */
final readonly class CompilationResult
{
    private function __construct(
        private string $phpCode,
        private ?string $sourceMap,
        private string $originalSource,
    ) {
    }

    public static function create(string $phpCode, ?string $sourceMap, string $originalSource): self
    {
        return new self($phpCode, $sourceMap, $originalSource);
    }

    public static function withoutSourceMap(string $phpCode, string $originalSource): self
    {
        return new self($phpCode, null, $originalSource);
    }

    public function phpCode(): string
    {
        return $this->phpCode;
    }

    public function sourceMap(): ?string
    {
        return $this->sourceMap;
    }

    public function originalSource(): string
    {
        return $this->originalSource;
    }

    public function hasSourceMap(): bool
    {
        return $this->sourceMap !== null;
    }
}
