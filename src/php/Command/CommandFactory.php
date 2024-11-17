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
use Phel\Command\Domain\Exceptions\ExceptionPrinterInterface;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractor;
use Phel\Command\Domain\Finder\DirectoryFinderInterface;
use Phel\Command\Domain\Finder\VendorDirectoriesFinderInterface;
use Phel\Command\Infrastructure\ComposerVendorDirectoriesFinder;
use Phel\Command\Infrastructure\ErrorLog;
use Phel\Command\Infrastructure\SourceMapExtractor;
use Phel\Compiler\Application\Munge;
use Phel\Printer\Printer;
use Phel\Run\Domain\Repl\ColorStyle;

/**
 * @method CommandConfig getConfig()
 */
final class CommandFactory extends AbstractFactory
{
    public function createCommandExceptionWriter(): CommandExceptionWriterInterface
    {
        return new CommandExceptionWriter(
            $this->createExceptionPrinter(),
            new ErrorLog($this->getConfig()->getErrorLogFile()),
        );
    }

    public function createExceptionPrinter(): ExceptionPrinterInterface
    {
        return new TextExceptionPrinter(
            new ExceptionArgsPrinter(Printer::readable()),
            ColorStyle::withStyles(),
            new Munge(),
            new FilePositionExtractor(new SourceMapExtractor()),
            new ErrorLog($this->getConfig()->getErrorLogFile()),
        );
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
        return $this->getProvidedDependency(CommandProvider::PHP_CONFIG_READER);
    }

    private function createComposerVendorDirectoriesFinder(): VendorDirectoriesFinderInterface
    {
        return new ComposerVendorDirectoriesFinder(
            $this->getConfig()->getAppRootDir() . '/' . $this->getConfig()->getVendorDir(),
        );
    }
}
