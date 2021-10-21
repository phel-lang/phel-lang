<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractFacade;
use Phel\Build\Command\CompileCommand;
use Phel\Build\Compile\BuildOptions;
use Phel\Build\Compile\CompiledFile;
use Phel\Build\Extractor\NamespaceInformation;

/**
 * @method BuildFactory getFactory()
 */
final class BuildFacade extends AbstractFacade implements BuildFacadeInterface
{
    /**
     * Extracts the namespace from a given file. It expects that the
     * first statement in the file is the 'ns statement.
     *
     * @param string $filename The path to the file
     *
     * @return NamespaceInformation
     */
    public function getNamespaceFromFile(string $filename): NamespaceInformation
    {
        return $this->getFactory()
            ->createNamespaceExtractor()
            ->getNamespaceFromFile($filename);
    }

    /**
     * Extracts all namespaces from all Phel files in the given directories.
     * It expects that the first statement in the file is the 'ns statement.
     *
     * @param string[] $directories The list of the directories
     *
     * @return list<NamespaceInformation>
     */
    public function getNamespaceFromDirectories(array $directories): array
    {
        return $this->getFactory()
            ->createNamespaceExtractor()
            ->getNamespacesFromDirectories($directories);
    }

    /**
     * Gets a list of all dependencies for a given list of namespaces. It first extracts all
     * namespaces from all Phel files in the give directories and then return a
     * topological sorted subset of this namespace information.
     *
     * @param string[] $directories The list of the directories
     * @param string[] $ns A list of namespace for which we should find the subset
     *
     * @return list<NamespaceInformation>
     */
    public function getDependenciesForNamespace(array $directories, array $ns): array
    {
        return $this->getFactory()
            ->createDependenciesForNamespace()
            ->getDependenciesForNamespace($directories, $ns);
    }

    /**
     * Compiles a phel file and saves it to the give destination.
     *
     * @param string $src The source file
     * @param string $dest The destination
     *
     * @return CompiledFile
     */
    public function compileFile(string $src, string $dest): CompiledFile
    {
        return $this->getFactory()
            ->createFileCompiler()
            ->compileFile($src, $dest, true);
    }

    /**
     * Same as `compileFile`. However, the generated code is not written to a destination.
     *
     * @param string $src The source file
     *
     * @return CompiledFile
     */
    public function evalFile(string $src): CompiledFile
    {
        return $this->getFactory()
            ->createFileEvaluator()
            ->evalFile($src);
    }

    /**
     * Compiles all Phel files that can be found in the give source directories
     * and saves them into the target directory.
     *
     * @param string[] $srcDirectories The list of source directories
     * @param string $dest the target dir that should contain the generated code
     *
     * @return list<CompiledFile>
     */
    public function compileProject(array $srcDirectories, string $dest): array
    {
        $enableCache = false;
        $enableSourceMap = true;

        return $this->getFactory()
            ->createProjectCompiler()
            ->compileProject($srcDirectories, $dest, new BuildOptions($enableCache, $enableSourceMap));
    }

    public function getCompileCommand(): CompileCommand
    {
        return $this->getFactory()
            ->createCompileCommand();
    }
}
