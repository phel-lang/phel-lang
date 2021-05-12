<?php

declare(strict_types=1);

namespace Phel\Runtime;

interface RuntimeFacadeInterface
{
    public function getRuntime(): RuntimeInterface;

    public function getNamespaceFromFile(string $path): string;

    /**
     * @param list<string> $directories
     */
    public function getNamespacesFromDirectories(array $directories): array;

    public function addPath(string $namespacePrefix, array $path): void;
}
