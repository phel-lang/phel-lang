<?php

declare(strict_types=1);

namespace Phel\Command\Shared;

interface NamespaceExtractorInterface
{
    public function getNamespaceFromFile(string $path): string;

    public function getNamespacesFromConfig(string $currentDir): array;
}
