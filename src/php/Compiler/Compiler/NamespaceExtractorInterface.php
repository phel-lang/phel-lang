<?php

declare(strict_types=1);

namespace Phel\Compiler\Compiler;

use Phel\Compiler\Analyzer\Ast\NsNode;

interface NamespaceExtractorInterface
{
    public function getNamespaceFromFile(string $path): NsNode;

    /**
     * @param list<string> $directories
     *
     * @return NsNode[]
     */
    public function getNamespacesFromDirectories(array $directories): array;
}
