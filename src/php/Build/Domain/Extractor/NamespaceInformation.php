<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

final readonly class NamespaceInformation
{
    /**
     * @param list<string> $dependencies
     */
    public function __construct(
        private string $file,
        private string $namespace,
        private array $dependencies,
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
}
