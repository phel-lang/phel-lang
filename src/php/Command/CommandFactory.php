<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Phel\Command\Application\CommandExceptionWriter;
use Phel\Command\Application\DirectoryFinder;
use Phel\Command\Application\TextExceptionPrinter;
use Phel\Command\Domain\CommandExceptionWriterInterface;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinter;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractor;
use Phel\Command\Domain\Finder\DirectoryFinderInterface;
use Phel\Command\Domain\Finder\VendorDirectoriesFinderInterface;
use Phel\Command\Infrastructure\ComposerVendorDirectoriesFinder;
use Phel\Command\Infrastructure\ErrorLog;
use Phel\Command\Infrastructure\SourceMapExtractor;
use Phel\Shared\ColorStyle;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\Hint\ArgumentCountHint;
use Phel\Shared\Exceptions\Hint\ExceptionHintInterface;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Exceptions\Hint\NotCallableHint;
use Phel\Shared\Exceptions\Hint\UndefinedSymbolHint;
use Phel\Shared\Munge;
use Phel\Shared\Printer\Printer;

/**
 * @extends AbstractFactory<CommandConfig>
 */
final class CommandFactory extends AbstractFactory
{
    public function createCommandExceptionWriter(): CommandExceptionWriterInterface
    {
        return new CommandExceptionWriter(
            $this->createExceptionPrinter(),
            new ErrorLog($this->getConfig()->getErrorLogFile()),
            $this->createFilePositionExtractor(),
            $this->getConfig()->getStaleOutputHint(),
            $this->createExceptionHintResolver(),
        );
    }

    public function createExceptionHintResolver(): ExceptionHintResolver
    {
        return new ExceptionHintResolver($this->createExceptionHints());
    }

    /**
     * @return list<ExceptionHintInterface>
     */
    public function createExceptionHints(): array
    {
        return [
            new NotCallableHint(),
            new ArgumentCountHint(),
            new UndefinedSymbolHint(),
        ];
    }

    public function createExceptionPrinter(): ExceptionPrinterInterface
    {
        return new TextExceptionPrinter(
            new ExceptionArgsPrinter(Printer::readable()),
            ColorStyle::withStyles(),
            new Munge(),
            $this->createFilePositionExtractor(),
            new ErrorLog($this->getConfig()->getErrorLogFile()),
        );
    }

    public function createFilePositionExtractor(): FilePositionExtractor
    {
        return new FilePositionExtractor(new SourceMapExtractor());
    }

    public function createDirectoryFinder(): DirectoryFinderInterface
    {
        return new DirectoryFinder(
            $this->getConfig()->getAppRootDir(),
            $this->getConfig()->getCodeDirs(),
            $this->createComposerVendorDirectoriesFinder(),
        );
    }

    public function getPhpConfigReader(): PhpConfigReader
    {
        /** @var PhpConfigReader $reader */
        $reader = $this->getProvidedDependency(CommandProvider::PHP_CONFIG_READER);

        return $reader;
    }

    private function createComposerVendorDirectoriesFinder(): VendorDirectoriesFinderInterface
    {
        return new ComposerVendorDirectoriesFinder(
            $this->getConfig()->getAppRootDir() . '/' . $this->getConfig()->getVendorDir(),
        );
    }
}
