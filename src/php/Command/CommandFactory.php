<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractFactory;
use Phel\Command\Export\ExportCommand;
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
use Phel\Formatter\FormatterFacadeInterface;
use Phel\Interop\InteropFacadeInterface;
use Phel\NamespaceExtractor\NamespaceExtractorFacadeInterface;
use Phel\Printer\Printer;
use Phel\Printer\PrinterInterface;
use Phel\Runtime\Exceptions\ExceptionPrinterInterface;
use Phel\Runtime\Exceptions\TextExceptionPrinter;
use Phel\Runtime\RuntimeFacadeInterface;

/**
 * @method CommandConfig getConfig()
 */
final class CommandFactory extends AbstractFactory
{
    public function createReplCommand(): ReplCommand
    {
        $this->getRuntimeFacade()
            ->getRuntime()
            ->loadFileIntoNamespace('user', $this->getConfig()->getReplStartupPhel());

        return new ReplCommand(
            $this->getRuntimeFacade(),
            $this->createReplCommandIo(),
            $this->getCompilerFacade(),
            $this->createColorStyle(),
            $this->createPrinter()
        );
    }

    public function createRunCommand(): RunCommand
    {
        return new RunCommand(
            $this->createCommandExceptionWriter(),
            $this->getRuntimeFacade(),
            $this->getNamespaceExtractorFacade()
        );
    }

    public function createTestCommand(): TestCommand
    {
        return new TestCommand(
            $this->createCommandExceptionWriter(),
            $this->getRuntimeFacade(),
            $this->getCompilerFacade(),
            $this->getNamespaceExtractorFacade(),
            $this->getConfig()->getTestDirectories()
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

    public function createExportCommand(): ExportCommand
    {
        return new ExportCommand(
            $this->createCommandExceptionWriter(),
            $this->getRuntimeFacade(),
            $this->getInteropFacade()
        );
    }

    private function createReplCommandIo(): ReplCommandIoInterface
    {
        return new ReplCommandSystemIo(
            $this->getConfig()->getPhelReplHistory(),
            $this->createExceptionPrinter()
        );
    }

    private function createCommandExceptionWriter(): CommandExceptionWriterInterface
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

    private function getInteropFacade(): InteropFacadeInterface
    {
        return $this->getProvidedDependency(CommandDependencyProvider::FACADE_INTEROP);
    }

    private function getRuntimeFacade(): RuntimeFacadeInterface
    {
        return $this->getProvidedDependency(CommandDependencyProvider::FACADE_RUNTIME);
    }

    private function getNamespaceExtractorFacade(): NamespaceExtractorFacadeInterface
    {
        return $this->getProvidedDependency(CommandDependencyProvider::FACADE_NAMESPACE_EXTRACTOR);
    }
}
