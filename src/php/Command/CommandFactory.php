<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Phel\Command\Application\CommandExceptionWriter;
use Phel\Command\Application\DirectoryFinder;
use Phel\Command\Application\RuntimeErrorReportFormatter;
use Phel\Command\Application\TextExceptionPrinter;
use Phel\Command\Domain\CommandExceptionWriterInterface;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinter;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractor;
use Phel\Command\Domain\Exceptions\InternalPathDetector;
use Phel\Command\Domain\Finder\DirectoryFinderInterface;
use Phel\Command\Domain\Finder\VendorDirectoriesFinderInterface;
use Phel\Command\Infrastructure\ComposerVendorDirectoriesFinder;
use Phel\Command\Infrastructure\ErrorLog;
use Phel\Command\Infrastructure\SourceMapExtractor;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\Hint\ArgumentCountHint;
use Phel\Shared\Exceptions\Hint\ExceptionHintInterface;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Exceptions\Hint\NotCallableHint;
use Phel\Shared\Exceptions\Hint\UndefinedSymbolHint;
use Phel\Shared\Munge;
use Phel\Shared\NoColor;
use Phel\Shared\Printer\Printer;
use Phel\Shared\ScalarCoercion;

use function basename;
use function implode;

/**
 * @extends AbstractFactory<CommandConfig>
 *
 * @internal
 */
#[ServiceMap(method: 'getConfig', className: CommandConfig::class)]
final class CommandFactory extends AbstractFactory
{
    public function createCommandExceptionWriter(): CommandExceptionWriterInterface
    {
        return new CommandExceptionWriter(
            $this->createExceptionPrinter(),
            $this->createErrorLog(),
            $this->createExceptionHintResolver(),
            $this->createRuntimeErrorReportFormatter(),
        );
    }

    public function createRuntimeErrorReportFormatter(): RuntimeErrorReportFormatter
    {
        return new RuntimeErrorReportFormatter(
            $this->createExceptionPrinter(),
            $this->createFilePositionExtractor(),
            $this->createInternalPathDetector(),
            $this->createExceptionHintResolver(),
            Printer::readable(),
            $this->getConfig()->getStaleOutputHint(),
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
            NoColor::style(),
            new Munge(),
            $this->createFilePositionExtractor(),
            $this->createErrorLog(),
            $this->getConfig()->getCollapsedTraceHint(),
        );
    }

    public function createErrorLog(): ErrorLogInterface
    {
        return new ErrorLog(
            $this->getConfig()->getErrorLogFile(),
            $this->currentCommandLine(),
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

    /**
     * Names the run that produced a log entry. `basename()` so `bin/phel`,
     * `vendor/bin/phel` and a global install all read as `phel`.
     */
    private function currentCommandLine(): string
    {
        $argv = ScalarCoercion::toStringList($_SERVER['argv'] ?? null);
        if ($argv === []) {
            return PHP_SAPI;
        }

        $argv[0] = basename($argv[0]);

        return implode(' ', $argv);
    }

    private function createInternalPathDetector(): InternalPathDetector
    {
        return new InternalPathDetector(
            $this->getConfig()->getPhelInternalSrcDir(),
            $this->getConfig()->getCacheDir(),
        );
    }

    private function createComposerVendorDirectoriesFinder(): VendorDirectoriesFinderInterface
    {
        return new ComposerVendorDirectoriesFinder(
            $this->getConfig()->getAppRootDir() . '/' . $this->getConfig()->getVendorDir(),
        );
    }
}
