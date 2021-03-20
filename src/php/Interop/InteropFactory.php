<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\AbstractFactory;
use Phel\Interop\DirectoryRemover\DirectoryRemover;
use Phel\Interop\DirectoryRemover\DirectoryRemoverInterface;
use Phel\Interop\ExportFinder\FunctionsToExportFinder;
use Phel\Interop\ExportFinder\FunctionsToExportFinderInterface;
use Phel\Interop\FileCreator\FileCreator;
use Phel\Interop\FileCreator\FileCreatorInterface;
use Phel\Interop\FileCreator\FileIoInterface;
use Phel\Interop\FileCreator\FileSystemIo;
use Phel\Interop\Generator\Builder\CompiledPhpClassBuilder;
use Phel\Interop\Generator\Builder\CompiledPhpMethodBuilder;
use Phel\Interop\Generator\Builder\WrapperRelativeFilenamePathBuilder;
use Phel\Interop\Generator\WrapperGenerator;
use Phel\Runtime\RuntimeFacade;

/**
 * @method InteropConfig getConfig()
 */
final class InteropFactory extends AbstractFactory
{
    public function createWrapperGenerator(): WrapperGenerator
    {
        return new WrapperGenerator(
            $this->createCompiledPhpClassBuilder(),
            new WrapperRelativeFilenamePathBuilder()
        );
    }

    public function createFunctionsToExportFinder(): FunctionsToExportFinderInterface
    {
        return new FunctionsToExportFinder(
            $this->getRuntimeFacade(),
            $this->getConfig()->getExportDirectories()
        );
    }

    public function createDirectoryRemover(): DirectoryRemoverInterface
    {
        return new DirectoryRemover(
            $this->getConfig()->getExportTargetDirectory()
        );
    }

    public function createFileCreator(): FileCreatorInterface
    {
        return new FileCreator(
            $this->getConfig()->getExportTargetDirectory(),
            $this->createFileSystemIo()
        );
    }

    private function createCompiledPhpClassBuilder(): CompiledPhpClassBuilder
    {
        return new CompiledPhpClassBuilder(
            $this->getConfig()->prefixNamespace(),
            new CompiledPhpMethodBuilder()
        );
    }

    private function createFileSystemIo(): FileIoInterface
    {
        return new FileSystemIo();
    }

    private function getRuntimeFacade(): RuntimeFacade
    {
        return $this->getProvidedDependency(InteropDependencyProvider::FACADE_RUNTIME);
    }
}
