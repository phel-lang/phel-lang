<?php

declare(strict_types=1);

namespace Phel\Command\Shared;

interface NamespaceExtractorInterface
{
    public function getNamespaceFromFile(string $path): string;

    /**
     * @param list<string> $directories
     */
    public function getNamespacesFromDirectories(array $directories, string $projectRootDir): array;
}
