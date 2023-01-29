<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractFactory;
use Phel\Build\Domain\Compile\DependenciesForNamespace;
use Phel\Build\Domain\Compile\FileCompiler;
use Phel\Build\Domain\Compile\FileCompilerInterface;
use Phel\Build\Domain\Compile\FileEvaluator;
use Phel\Build\Domain\Compile\ProjectCompiler;
use Phel\Build\Domain\Extractor\NamespaceExtractor;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use Phel\Build\Domain\Extractor\TopologicalNamespaceSorter;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Build\Infrastructure\IO\SystemFileIo;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\CompilerFacadeInterface;

/**
 * @method BuildConfig getConfig()
 */
final class BuildFactory extends AbstractFactory
{
    public function createProjectCompiler(): ProjectCompiler
    {
        return new ProjectCompiler(
            $this->createNamespaceExtractor(),
            $this->createFileCompiler(),
            $this->getCompilerFacade(),
            $this->getCommandFacade(),
            $this->getConfig()->getPathsToIgnore(),
        );
    }

    public function createDependenciesForNamespace(): DependenciesForNamespace
    {
        return new DependenciesForNamespace(
            $this->createNamespaceExtractor(),
        );
    }

    public function createFileCompiler(): FileCompilerInterface
    {
        return new FileCompiler(
            $this->getCompilerFacade(),
            $this->createNamespaceExtractor(),
            $this->createFileIo(),
        );
    }

    public function createFileEvaluator(): FileEvaluator
    {
        return new FileEvaluator(
            $this->getCompilerFacade(),
            $this->createNamespaceExtractor(),
        );
    }

    public function createNamespaceExtractor(): NamespaceExtractor
    {
        return new NamespaceExtractor(
            $this->getCompilerFacade(),
            $this->createNamespaceSorter(),
            $this->createFileIo(),
        );
    }

    public function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(BuildDependencyProvider::FACADE_COMPILER);
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(BuildDependencyProvider::FACADE_COMMAND);
    }

    private function createNamespaceSorter(): NamespaceSorterInterface
    {
        return new TopologicalNamespaceSorter();
    }

    private function createFileIo(): FileIoInterface
    {
        return new SystemFileIo();
    }
}
