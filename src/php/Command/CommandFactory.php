<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractFactory;
use Phel\Build\BuildFacadeInterface;
use Phel\Command\Format\FormatCommand;
use Phel\Command\Format\PathFilterInterface;
use Phel\Command\Format\PhelPathFilter;
use Phel\Command\Repl\ColorStyle;
use Phel\Command\Repl\ColorStyleInterface;
use Phel\Command\Repl\ReplCommand;
use Phel\Command\Repl\ReplCommandIoInterface;
use Phel\Command\Repl\ReplCommandSystemIo;
use Phel\Command\Run\RunCommand;
use Phel\Command\Shared\CommandExceptionWriter;
use Phel\Command\Shared\CommandExceptionWriterInterface;
use Phel\Command\Test\TestCommand;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Config\ConfigFacadeInterface;
use Phel\Formatter\FormatterFacadeInterface;
use Phel\Printer\Printer;
use Phel\Printer\PrinterInterface;
use Phel\Runtime\Exceptions\ExceptionPrinterInterface;
use Phel\Runtime\Exceptions\TextExceptionPrinter;

/**
 * @method CommandConfig getConfig()
 */
final class CommandFactory extends AbstractFactory
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
            $this->createCommandExceptionWriter(),
            $this->getBuildFacade(),
            $this->getConfigFacade()
        );
    }

    public function createTestCommand(): TestCommand
    {
        return new TestCommand(
            $this->createCommandExceptionWriter(),
            $this->getCompilerFacade(),
            $this->getBuildFacade(),
            $this->getConfigFacade()
        );
    }

    public function createFormatCommand(): FormatCommand
    {
        return new FormatCommand(
            $this->createCommandExceptionWriter(),
            $this->getFormatterFacade(),
            $this->createPathFilter()
        );
    }

    private function createReplCommandIo(): ReplCommandIoInterface
    {
        return new ReplCommandSystemIo(
            $this->getConfig()->getPhelReplHistory(),
            $this->createExceptionPrinter()
        );
    }

    public function createCommandExceptionWriter(): CommandExceptionWriterInterface
    {
        return new CommandExceptionWriter(
            $this->createExceptionPrinter()
        );
    }

    private function createExceptionPrinter(): ExceptionPrinterInterface
    {
        return TextExceptionPrinter::create();
    }

    private function createPathFilter(): PathFilterInterface
    {
        return new PhelPathFilter();
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
        return $this->getProvidedDependency(CommandDependencyProvider::FACADE_COMPILER);
    }

    private function getFormatterFacade(): FormatterFacadeInterface
    {
        return $this->getProvidedDependency(CommandDependencyProvider::FACADE_FORMATTER);
    }

    private function getBuildFacade(): BuildFacadeInterface
    {
        return $this->getProvidedDependency(CommandDependencyProvider::FACADE_BUILD);
    }

    private function getConfigFacade(): ConfigFacadeInterface
    {
        return $this->getProvidedDependency(CommandDependencyProvider::FACADE_CONFIG);
    }
}
