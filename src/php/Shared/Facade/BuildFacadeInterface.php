<?php

declare(strict_types=1);

namespace Phel\Shared\Facade;

use Gacela\Framework\Health\ModuleHealthCheckInterface;
use Phel\Shared\CompiledFile;
use Phel\Shared\NamespaceInformation;

interface BuildFacadeInterface
{
    /**
     * Extracts the namespace from a given file. It expects that the
     * first statement in the file is the 'ns statement.
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
     * @param string[] $ns          A list of namespace for which we should find the subset
     *
     * @return list<NamespaceInformation>
     */
    public function getDependenciesForNamespace(array $directories, array $ns): array;

    /**
     * Compiles a phel file and saves it to the give destination.
     */
    public function compileFile(string $src, string $dest): CompiledFile;

    /**
     * Same as `compileFile`. However, the generated code is not written to a destination.
     */
    public function evalFile(string $src): CompiledFile;

    /**
     * Writes the compiled-code cache index to disk now instead of at process
     * shutdown, so a subprocess spawned afterwards finds what this process
     * has compiled so far. No-op when nothing is pending or the cache is off.
     */
    public function flushCompiledCodeCache(): void;

    /**
     * Returns the module's health check for diagnostics (`phel doctor`).
     */
    public function getHealthCheck(): ModuleHealthCheckInterface;
}
