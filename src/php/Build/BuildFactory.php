<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractFactory;
use Phel\Build\Command\CompileCommand;
use Phel\Build\Compile\DependenciesForNamespace;
use Phel\Build\Compile\FileCompiler;
use Phel\Build\Compile\FileCompilerInterface;
use Phel\Build\Compile\FileEvaluator;
use Phel\Build\Compile\ProjectCompiler;
use Phel\Build\Extractor\NamespaceExtractor;
use Phel\Build\Extractor\NamespaceSorterInterface;
use Phel\Build\Extractor\TopologicalNamespaceSorter;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\CompilerFacadeInterface;

final class BuildFactory extends AbstractFactory
{
    public function createProjectCompiler(): ProjectCompiler
    {
        return new ProjectCompiler(
            $this->createNamespaceExtractor(),
            $this->createFileCompiler(),
            $this->getCompilerFacade(),
        );
    }

    public function createDependenciesForNamespace(): DependenciesForNamespace
    {
        return new DependenciesForNamespace(
            $this->createNamespaceExtractor()
        );
    }

    public function createFileCompiler(): FileCompilerInterface
    {
        return new FileCompiler(
            $this->getCompilerFacade(),
            $this->createNamespaceExtractor()
        );
    }

    public function createFileEvaluator(): FileEvaluator
    {
        return new FileEvaluator(
            $this->getCompilerFacade(),
            $this->createNamespaceExtractor()
        );
    }

    public function createNamespaceExtractor(): NamespaceExtractor
    {
        return new NamespaceExtractor(
            $this->getCompilerFacade(),
            $this->createNamespaceSorter()
        );
    }

    private function createNamespaceSorter(): NamespaceSorterInterface
    {
        return new TopologicalNamespaceSorter();
    }

    public function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(BuildDependencyProvider::FACADE_COMPILER);
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(BuildDependencyProvider::FACADE_COMMAND);
    }

    public function createCompileCommand(): CompileCommand
    {
        return new CompileCommand(
            $this->createProjectCompiler(),
            $this->getCommandFacade()
        );
    }
}
