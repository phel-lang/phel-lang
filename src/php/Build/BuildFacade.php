<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractFacade;
use Phel\Build\Domain\Builder\BuildOptions;
use Phel\Build\Domain\Builder\TraspiledFile;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Lang\Registry;
use Phel\Shared\BuildConstants;
use Phel\Shared\CompilerConstants;
use Phel\Transpiler\Domain\Exceptions\TranspilerException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @method BuildFactory getFactory()
 */
final class BuildFacade extends AbstractFacade implements BuildFacadeInterface
{
    public static function enableBuildMode(): void
    {
        Registry::getInstance()->addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, BuildConstants::COMPILE_MODE, true);
        Registry::getInstance()->addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, BuildConstants::BUILD_MODE, true);
    }

    public static function disableBuildMode(): void
    {
        Registry::getInstance()->addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, BuildConstants::COMPILE_MODE, false);
        Registry::getInstance()->addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, BuildConstants::BUILD_MODE, false);
    }

    /**
     * Extracts the namespace from a given file. It expects that the
     * first statement in the file is the 'ns statement.
     *
     * @param string $filename The path to the file
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
     * Build a phel file and saves it to the give destination.
     *
     * @param string $src The source file
     * @param string $dest The destination
     */
    public function buildFile(string $src, string $dest): TraspiledFile
    {
        return $this->getFactory()
            ->createFileBuilder()
            ->compileFile($src, $dest, true);
    }

    /**
     * Same as `buildFile`. However, the generated code is not written to a destination.
     *
     * @param string $src The source file
     */
    public function evalFile(string $src): TraspiledFile
    {
        return $this->getFactory()
            ->createFileEvaluator()
            ->evalFile($src);
    }

    /**
     * @return list<TraspiledFile>
     */
    public function buildProject(BuildOptions $options): array
    {
        return $this->getFactory()
            ->createProjectBuilder()
            ->buildProject($options);
    }

    public function registerExceptionHandler(): void
    {
        $this->getFactory()
            ->getCommandFacade()
            ->registerExceptionHandler();
    }

    public function writeLocatedException(OutputInterface $output, TranspilerException $e): void
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
