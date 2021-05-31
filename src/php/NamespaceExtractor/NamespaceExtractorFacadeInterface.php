<?php

declare(strict_types=1);

namespace Phel\NamespaceExtractor;

use Phel\Compiler\Analyzer\Ast\NsNode;

interface NamespaceExtractorFacadeInterface
{
    /**
     * Extracts the namespace from a given file. It expects that the
     * first statement in the file is the 'ns statement.
     *
     * @param string $filename The path to the file
     *
     * @return NsNode
     */
    public function getNamespaceFromFile(string $filename): NsNode;

    /**
     * Extracts all namespaces from all Phel files in the given directories.
     * It expects that the first statement in the file is the 'ns statement.
     *
     * @param string[] $directories The list of the directories
     *
     * @return NsNode[]
     */
    public function getNamespaceFromDirectories(array $directories): array;
}
