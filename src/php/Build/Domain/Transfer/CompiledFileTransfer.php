<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Transfer;

/**
 * Transfer object for compiled file data across module boundaries.
 */
final readonly class CompiledFileTransfer
{
    public function __construct(
        public string $sourceFile,
        public string $targetFile,
        public string $namespace,
    ) {
    }

    public static function fromCompiledFile(
        string $sourceFile,
        string $targetFile,
        string $namespace,
    ): self {
        return new self($sourceFile, $targetFile, $namespace);
    }
}
