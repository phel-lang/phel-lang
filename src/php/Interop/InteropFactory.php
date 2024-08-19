<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractFactory;
use Phel\Build\BuildFacadeInterface;
use Phel\Command\CommandFacadeInterface;
use Phel\Interop\Application\ExportCodeGenerator;
use Phel\Interop\Domain\DirectoryRemover\DirectoryRemover;
use Phel\Interop\Domain\DirectoryRemover\DirectoryRemoverInterface;
use Phel\Interop\Domain\ExportFinder\FunctionsToExportFinder;
use Phel\Interop\Domain\ExportFinder\FunctionsToExportFinderInterface;
use Phel\Interop\Domain\FileCreator\FileCreator;
use Phel\Interop\Domain\FileCreator\FileCreatorInterface;
use Phel\Interop\Domain\FileCreator\FileIoInterface;
use Phel\Interop\Domain\Generator\Builder\CompiledPhpClassBuilder;
use Phel\Interop\Domain\Generator\Builder\CompiledPhpMethodBuilder;
use Phel\Interop\Domain\Generator\Builder\WrapperRelativeFilenamePathBuilder;
use Phel\Interop\Domain\Generator\WrapperGenerator;
use Phel\Interop\Infrastructure\Io\FileSystemIo;

/**
 * @method InteropConfig getConfig()
 */
final class InteropFactory extends AbstractFactory
{
    public function createExportCodeGenerator(): ExportCodeGenerator
    {
        return new ExportCodeGenerator(
            $this->createDirectoryRemover(),
            $this->createWrapperGenerator(),
            $this->createFunctionsToExportFinder(),
            $this->createFileCreator(),
        );
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(InteropProvider::FACADE_COMMAND);
    }

    private function createDirectoryRemover(): DirectoryRemoverInterface
    {
        return new DirectoryRemover(
            $this->getConfig()->getExportTargetDirectory(),
        );
    }

    private function createFileCreator(): FileCreatorInterface
    {
        return new FileCreator(
            $this->getConfig()->getExportTargetDirectory(),
            $this->createFileSystemIo(),
        );
    }

    private function createWrapperGenerator(): WrapperGenerator
    {
        return new WrapperGenerator(
            $this->createCompiledPhpClassBuilder(),
            $this->createWrapperPathBuilder(),
        );
    }

    private function createCompiledPhpClassBuilder(): CompiledPhpClassBuilder
    {
        return new CompiledPhpClassBuilder(
            $this->getConfig()->prefixNamespace(),
            $this->createCompiledPhpMethodBuilder(),
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
            $this->getConfig()->getExportDirectories(),
        );
    }

    private function getBuildFacade(): BuildFacadeInterface
    {
        return $this->getProvidedDependency(InteropProvider::FACADE_BUILD);
    }

    private function createFileSystemIo(): FileIoInterface
    {
        return new FileSystemIo();
    }
}
