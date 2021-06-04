<?php

declare(strict_types=1);

namespace Phel\NamespaceExtractor;

use Phel\NamespaceExtractor\Extractor\NamespaceInformation;

interface NamespaceExtractorFacadeInterface
{
    /**
     * Extracts the namespace from a given file. It expects that the
     * first statement in the file is the 'ns statement.
     *
     * @param string $filename The path to the file
     *
     * @return NamespaceInformation
     */
    public function getNamespaceFromFile(string $filename): NamespaceInformation;

    /**
     * Extracts all namespaces from all Phel files in the given directories.
     * It expects that the first statement in the file is the 'ns statement.
     *
     * @param string[] $directories The list of the directories
     *
     * @return NamespaceInformation[]
     */
    public function getNamespaceFromDirectories(array $directories): array;
}
