<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractFacade;
use Phel\Build\Domain\Compile\BuildOptions;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

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
     * @param list<string> $directories The list of the directories
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
     * @return list<CompiledFile>
     */
    public function compileProject(BuildOptions $options): array
    {
        return $this->getFactory()
            ->createProjectCompiler()
            ->compileProject($options);
    }


    public function registerExceptionHandler(): void
    {
        $this->getFactory()
            ->getCommandFacade()
            ->registerExceptionHandler();
    }

    public function writeLocatedException(OutputInterface $output, CompilerException $e): void
    {
        $this->getFactory()
            ->getCommandFacade()
            ->writeLocatedException(
                $output,
                $e->getNestedException(),
                $e->getCodeSnippet(),
            );
    }

    public function writeStackTrace(OutputInterface $output, Throwable $e): void
    {
        $this->getFactory()
            ->getCommandFacade()
            ->writeStackTrace($output, $e);
    }
}
