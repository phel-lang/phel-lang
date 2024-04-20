<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractFactory;
use Phel\Build\Domain\Builder\DependenciesForNamespace;
use Phel\Build\Domain\Builder\FileBuilder;
use Phel\Build\Domain\Builder\FileBuilderInterface;
use Phel\Build\Domain\Builder\FileEvaluator;
use Phel\Build\Domain\Builder\Output\EntryPointPhpFile;
use Phel\Build\Domain\Builder\Output\EntryPointPhpFileInterface;
use Phel\Build\Domain\Builder\Output\NamespacePathTransformer;
use Phel\Build\Domain\Builder\ProjectBuilder;
use Phel\Build\Domain\Extractor\NamespaceExtractor;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use Phel\Build\Domain\Extractor\TopologicalNamespaceSorter;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Build\Infrastructure\IO\SystemFileIo;
use Phel\Command\CommandFacadeInterface;
use Phel\Transpiler\TranspilerFacadeInterface;

/**
 * @method BuildConfig getConfig()
 */
final class BuildFactory extends AbstractFactory
{
    public function createProjectBuilder(): ProjectBuilder
    {
        return new ProjectBuilder(
            $this->createNamespaceExtractor(),
            $this->createFileBuilder(),
            $this->getTranspilerFacade(),
            $this->getCommandFacade(),
            $this->createMainPhpEntryPointFile(),
            $this->getConfig(),
        );
    }

    public function createDependenciesForNamespace(): DependenciesForNamespace
    {
        return new DependenciesForNamespace(
            $this->createNamespaceExtractor(),
        );
    }

    public function createFileBuilder(): FileBuilderInterface
    {
        return new FileBuilder(
            $this->getTranspilerFacade(),
            $this->createNamespaceExtractor(),
            $this->createFileIo(),
        );
    }

    public function createFileEvaluator(): FileEvaluator
    {
        return new FileEvaluator(
            $this->getTranspilerFacade(),
            $this->createNamespaceExtractor(),
        );
    }

    public function createNamespaceExtractor(): NamespaceExtractor
    {
        return new NamespaceExtractor(
            $this->getTranspilerFacade(),
            $this->createNamespaceSorter(),
            $this->createFileIo(),
        );
    }

    public function getTranspilerFacade(): TranspilerFacadeInterface
    {
        return $this->getProvidedDependency(BuildDependencyProvider::FACADE_TRANSPILER);
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(BuildDependencyProvider::FACADE_COMMAND);
    }

    private function createMainPhpEntryPointFile(): EntryPointPhpFileInterface
    {
        return new EntryPointPhpFile(
            $this->getConfig()->getPhelOutConfig(),
            $this->createNamespacePathTransformer(),
            $this->getConfig()->getAppRootDir(),
        );
    }

    private function createNamespaceSorter(): NamespaceSorterInterface
    {
        return new TopologicalNamespaceSorter();
    }

    private function createFileIo(): FileIoInterface
    {
        return new SystemFileIo();
    }

    private function createNamespacePathTransformer(): NamespacePathTransformer
    {
        return new NamespacePathTransformer();
    }
}
