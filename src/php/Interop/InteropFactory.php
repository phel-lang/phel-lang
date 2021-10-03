<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractFactory;
use Phel\Build\BuildFacadeInterface;
use Phel\Command\CommandFacadeInterface;
use Phel\Interop\Command\ExportCommand;
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

/**
 * @method InteropConfig getConfig()
 */
final class InteropFactory extends AbstractFactory
{
    public function createExportCommand(): ExportCommand
    {
        return new ExportCommand(
            $this->createDirectoryRemover(),
            $this->createWrapperGenerator(),
            $this->createFunctionsToExportFinder(),
            $this->createFileCreator(),
            $this->getCommandFacade(),
        );
    }

    public function createDirectoryRemover(): DirectoryRemoverInterface
    {
        return new DirectoryRemover(
            $this->getConfig()->getExportTargetDirectory()
        );
    }

    private function createWrapperGenerator(): WrapperGenerator
    {
        return new WrapperGenerator(
            $this->createCompiledPhpClassBuilder(),
            $this->createWrapperPathBuilder()
        );
    }

    private function createCompiledPhpClassBuilder(): CompiledPhpClassBuilder
    {
        return new CompiledPhpClassBuilder(
            $this->getConfig()->prefixNamespace(),
            $this->createCompiledPhpMethodBuilder()
        );
    }

    private function createCompiledPhpMethodBuilder(): CompiledPhpMethodBuilder
    {
        return new CompiledPhpMethodBuilder();
    }

    private function createWrapperPathBuilder(): WrapperRelativeFilenamePathBuilder
    {
        return new WrapperRelativeFilenamePathBuilder();
    }

    private function createFunctionsToExportFinder(): FunctionsToExportFinderInterface
    {
        return new FunctionsToExportFinder(
            $this->getBuildFacade(),
            $this->getCommandFacade(),
            $this->getConfig()->getExportDirectories()
        );
    }

    private function getBuildFacade(): BuildFacadeInterface
    {
        return $this->getProvidedDependency(InteropDependencyProvider::FACADE_BUILD);
    }

    public function createFileCreator(): FileCreatorInterface
    {
        return new FileCreator(
            $this->getConfig()->getExportTargetDirectory(),
            $this->createFileSystemIo()
        );
    }

    private function createFileSystemIo(): FileIoInterface
    {
        return new FileSystemIo();
    }

    private function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(InteropDependencyProvider::FACADE_COMMAND);
    }
}
