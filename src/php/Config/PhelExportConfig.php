<?php

declare(strict_types=1);

namespace Phel\Config;

final class PhelExportConfig
{
    private array $directories = ['src/phel'];
    private string $namespacePrefix = 'PhelGenerated';
    private string $targetDirectory = 'src/PhelGenerated';

    public function getDirectories(): array
    {
        return $this->directories;
    }

    public function setDirectories(array $directories): self
    {
        $this->directories = $directories;

        return $this;
    }

    public function getNamespacePrefix(): string
    {
        return $this->namespacePrefix;
    }

    public function setNamespacePrefix(string $namespacePrefix): self
    {
        $this->namespacePrefix = $namespacePrefix;

        return $this;
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }

    public function setTargetDirectory(string $targetDirectory): self
    {
        $this->targetDirectory = $targetDirectory;

        return $this;
    }
}
