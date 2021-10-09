<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractFactory;
use Phel\Build\BuildFacadeInterface;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Printer\Printer;
use Phel\Printer\PrinterInterface;
use Phel\Run\Command\ReplCommand;
use Phel\Run\Command\RunCommand;
use Phel\Run\Command\TestCommand;
use Phel\Run\Domain\Repl\ColorStyle;
use Phel\Run\Domain\Repl\ColorStyleInterface;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\Domain\Repl\ReplCommandSystemIo;
use Phel\Run\Runner\NamespaceRunner;
use Phel\Run\Runner\NamespaceRunnerInterface;

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

    public function createRunCommand(): RunCommand
    {
        return new RunCommand(
            $this->getCommandFacade(),
            $this->createNamespaceRunner(),
            $this->getBuildFacade(),
        );
    }

    public function createTestCommand(): TestCommand
    {
        return new TestCommand(
            $this->getCommandFacade(),
            $this->getCompilerFacade(),
            $this->getBuildFacade()
        );
    }

    public function createNamespaceRunner(): NamespaceRunnerInterface
    {
        return new NamespaceRunner(
            $this->getCommandFacade(),
            $this->getBuildFacade()
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

    private function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(RunDependencyProvider::FACADE_COMPILER);
    }

    private function getBuildFacade(): BuildFacadeInterface
    {
        return $this->getProvidedDependency(RunDependencyProvider::FACADE_BUILD);
    }

    private function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(RunDependencyProvider::FACADE_COMMAND);
    }
}
