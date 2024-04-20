<?php

declare(strict_types=1);

namespace Phel\Build;

use Phel\Build\Domain\Builder\TraspiledFile;
use Phel\Build\Domain\Extractor\NamespaceInformation;

interface BuildFacadeInterface
{
    /**
     * Extracts the namespace from a given file. It expects that the
     * first statement in the file is the 'ns statement.
     *
     * @param string $filename The path to the file
     */
    public function getNamespaceFromFile(string $filename): NamespaceInformation;

    /**
     * Extracts all namespaces from all Phel files in the given directories.
     * It expects that the first statement in the file is the 'ns statement.
     *
     * The result is topologically sorted. That means that file that have dependencies
     * to other files are sorted behind the files that have no dependencies.
     *
     * @param list<string> $directories The list of the directories
     *
     * @return list<NamespaceInformation>
     */
    public function getNamespaceFromDirectories(array $directories): array;

    /**
     * Gets a list of all dependencies for a given list of namespaces. It first extracts all
     * namespaces from all Phel files in the give directories and then return a
     * topological sorted subset of these namespaces' information.
     *
     * @param string[] $directories The list of the directories
     * @param string[] $ns A list of namespace for which we should find the subset
     *
     * @return list<NamespaceInformation>
     */
    public function getDependenciesForNamespace(array $directories, array $ns): array;

    /**
     * Build a phel file and saves it to the give destination.
     *
     * @param string $src The source file
     * @param string $dest The destination
     */
    public function buildFile(string $src, string $dest): TraspiledFile;

    /**
     * Same as `buildFile`. However, the generated code is not written to a destination.
     *
     * @param string $src The source file
     */
    public function evalFile(string $src): TraspiledFile;
}
