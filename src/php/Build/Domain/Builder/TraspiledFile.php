<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Builder;

final readonly class TraspiledFile
{
    public function __construct(
        private string $sourceFile,
        private string $targetFile,
        private string $namespace,
    ) {
    }

    public function getSourceFile(): string
    {
        return $this->sourceFile;
    }

    public function getTargetFile(): string
    {
        return $this->targetFile;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }
}
