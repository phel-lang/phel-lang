<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractFactory;
use Phel\Build\BuildFacadeInterface;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Config\ConfigFacadeInterface;
use Phel\Printer\Printer;
use Phel\Printer\PrinterInterface;
use Phel\Run\Command\Repl\ColorStyle;
use Phel\Run\Command\Repl\ColorStyleInterface;
use Phel\Run\Command\Repl\ReplCommand;
use Phel\Run\Command\Repl\ReplCommandIoInterface;
use Phel\Run\Command\Repl\ReplCommandSystemIo;
use Phel\Run\Command\Run\RunCommand;
use Phel\Run\Command\Test\TestCommand;

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
            $this->getConfigFacade(),
            $this->getConfig()->getReplStartupFile()
        );
    }

    public function createRunCommand(): RunCommand
    {
        return new RunCommand(
            $this->getCommandFacade(),
            $this->getBuildFacade(),
            $this->getConfigFacade()
        );
    }

    public function createTestCommand(): TestCommand
    {
        return new TestCommand(
            $this->getCommandFacade(),
            $this->getCompilerFacade(),
            $this->getBuildFacade(),
            $this->getConfigFacade()
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

    private function getConfigFacade(): ConfigFacadeInterface
    {
        return $this->getProvidedDependency(RunDependencyProvider::FACADE_CONFIG);
    }

    private function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(RunDependencyProvider::FACADE_COMMAND);
    }
}
