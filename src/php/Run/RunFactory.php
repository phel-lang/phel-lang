<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractFactory;
use Phel\Build\BuildFacadeInterface;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Printer\Printer;
use Phel\Printer\PrinterInterface;
use Phel\Run\Domain\Repl\ColorStyle;
use Phel\Run\Domain\Repl\ColorStyleInterface;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\Domain\Repl\ReplCommandSystemIo;
use Phel\Run\Domain\Runner\NamespaceCollector;
use Phel\Run\Domain\Runner\NamespaceRunner;
use Phel\Run\Domain\Runner\NamespaceRunnerInterface;
use Phel\Run\Infrastructure\Command\ReplCommand;

/**
 * @method RunConfig getConfig()
 */
final class RunFactory extends AbstractFactory
{
    public function createReplCommand(): ReplCommand
    {
        return new ReplCommand(
            $this->createReplCommandIo(),
            $this->getCompilerFacade(),
            $this->createColorStyle(),
            $this->createPrinter(),
            $this->getBuildFacade(),
            $this->getCommandFacade(),
            $this->getConfig()->getReplStartupFile()
        );
    }

    public function createNamespaceRunner(): NamespaceRunnerInterface
    {
        return new NamespaceRunner(
            $this->getCommandFacade(),
            $this->getBuildFacade()
        );
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(RunDependencyProvider::FACADE_COMMAND);
    }

    public function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(RunDependencyProvider::FACADE_COMPILER);
    }

    public function getBuildFacade(): BuildFacadeInterface
    {
        return $this->getProvidedDependency(RunDependencyProvider::FACADE_BUILD);
    }

    public function createNamespaceCollector(): NamespaceCollector
    {
        return new NamespaceCollector(
            $this->getBuildFacade(),
            $this->getCommandFacade()
        );
    }

    private function createReplCommandIo(): ReplCommandIoInterface
    {
        return new ReplCommandSystemIo(
            $this->getConfig()->getPhelReplHistory(),
            $this->getCommandFacade()
        );
    }

    private function createColorStyle(): ColorStyleInterface
    {
        return ColorStyle::withStyles();
    }

    private function createPrinter(): PrinterInterface
    {
        return Printer::nonReadableWithColor();
    }
}
