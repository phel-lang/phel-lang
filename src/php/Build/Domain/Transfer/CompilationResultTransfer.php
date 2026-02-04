<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Transfer;

/**
 * Transfer object for compilation results across module boundaries.
 * Decouples Build module from Compiler's EmitterResult internal type.
 */
final readonly class CompilationResultTransfer
{
    public function __construct(
        public string $phpCode,
        public string $sourceMap,
    ) {
    }

    public static function fromCompilation(string $phpCode, string $sourceMap): self
    {
        return new self($phpCode, $sourceMap);
    }

    public function hasSourceMap(): bool
    {
        return $this->sourceMap !== '';
    }
}
