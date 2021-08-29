<?php

declare(strict_types=1);

namespace Phel\Build;

use Phel\Build\Extractor\NamespaceInformation;

interface BuildFacadeInterface
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
     * The result is topologically sorted. That means that file that have dependencies
     * to other files are sorted behind the files that have no dependecies.
     *
     * @param string[] $directories The list of the directories
     *
     * @return NamespaceInformation[]
     */
    public function getNamespaceFromDirectories(array $directories): array;
}
