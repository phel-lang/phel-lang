<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractFactory;
use Phel\Build\BuildFacadeInterface;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Printer\PrinterInterface;
use Phel\Run\Domain\Repl\ColorStyleInterface;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\Domain\Runner\NamespaceCollector;
use Phel\Run\Domain\Runner\NamespaceRunner;
use Phel\Run\Domain\Runner\NamespaceRunnerInterface;

/**
 * @method RunConfig getConfig()
 */
final class RunFactory extends AbstractFactory
{
    public function createNamespaceRunner(): NamespaceRunnerInterface
    {
        return new NamespaceRunner(
            $this->getCommandFacade(),
            $this->getBuildFacade()
        );
    }

    public function getReplStartupFile(): string
    {
        return $this->getConfig()->getReplStartupFile();
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(RunDependencyProvider::FACADE_COMMAND);
    }

    public function getBuildFacade(): BuildFacadeInterface
    {
        return $this->getProvidedDependency(RunDependencyProvider::FACADE_BUILD);
    }

    public function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(RunDependencyProvider::FACADE_COMPILER);
    }

    public function createNamespaceCollector(): NamespaceCollector
    {
        return new NamespaceCollector(
            $this->getBuildFacade(),
            $this->getCommandFacade()
        );
    }

    public function createColorStyle(): ColorStyleInterface
    {
        return $this->getProvidedDependency(RunDependencyProvider::COLOR_STYLE);
    }

    public function createPrinter(): PrinterInterface
    {
        return $this->getProvidedDependency(RunDependencyProvider::PRINTER);
    }

    public function createReplCommandIo(): ReplCommandIoInterface
    {
        return $this->getProvidedDependency(RunDependencyProvider::REPL_COMMAND_IO);
    }
}
