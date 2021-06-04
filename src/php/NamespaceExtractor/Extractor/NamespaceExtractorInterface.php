<?php

declare(strict_types=1);

namespace Phel\NamespaceExtractor\Extractor;

interface NamespaceExtractorInterface
{
    public function getNamespaceFromFile(string $path): NamespaceInformation;

    /**
     * @param list<string> $directories
     *
     * @return NamespaceInformation[]
     */
    public function getNamespacesFromDirectories(array $directories): array;
}
