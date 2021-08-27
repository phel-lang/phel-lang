<?php

declare(strict_types=1);

namespace Phel\Config;

final class ExportConfiguration
{
    private array $directories = [];
    private string $namespacePrefix = '';
    private string $targetDirectory = '';

    public static function empty(): self
    {
        return new self();
    }

    public function getDirectories(): array
    {
        return $this->directories;
    }

    /**
     * @param string|array $directories
     */
    public function setDirectories($directories): self
    {
        if (is_string($directories)) {
            $directories = [$directories];
        }

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
