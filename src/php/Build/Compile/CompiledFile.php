<?php

declare(strict_types=1);

namespace Phel\Build\Compile;

final class CompiledFile
{
    private string $sourceFile;
    private string $targetFile;
    private string $namespace;

    public function __construct(string $sourceFile, string $targetFile, string $namespace)
    {
        $this->sourceFile = $sourceFile;
        $this->targetFile = $targetFile;
        $this->namespace = $namespace;
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
