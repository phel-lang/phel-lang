<?php

declare(strict_types=1);

namespace Phel\Build\Extractor;

final class NamespaceInformation
{
    private string $file;
    private string $namespace;
    /** @var string[] */
    private array $dependencies;

    public function __construct(string $file, string $namespace, array $dependencies)
    {
        $this->file = $file;
        $this->namespace = $namespace;
        $this->dependencies = $dependencies;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }
}
