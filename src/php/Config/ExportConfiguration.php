<?php

declare(strict_types=1);

namespace Phel\Config;

final class ExportConfiguration
{
    /** @var list<string> */
    private array $directories = [];

    private string $namespacePrefix = '';

    private string $targetDirectory = '';

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @return list<string>
     */
    public function getDirectories(): array
    {
        return $this->directories;
    }

    public function setDirectories(string ...$directories): self
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
