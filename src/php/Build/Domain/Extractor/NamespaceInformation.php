<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

final readonly class NamespaceInformation
{
    /**
     * @param list<string> $dependencies
     * @param bool         $isPrimaryDefinition True if this file uses `ns` to define the namespace,
     *                                          false if it uses `in-ns` to join an existing namespace
     */
    public function __construct(
        private string $file,
        private string $namespace,
        private array $dependencies,
        private bool $isPrimaryDefinition = true,
    ) {
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return list<string>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function isPrimaryDefinition(): bool
    {
        return $this->isPrimaryDefinition;
    }
}
