<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractFacade;
use Phel\Command\Domain\Exceptions\ExceptionPrinterInterface;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @method CommandFactory getFactory()
 */
final class CommandFacade extends AbstractFacade implements CommandFacadeInterface
{
    public function writeLocatedException(
        OutputInterface $output,
        AbstractLocatedException $locatedException,
        CodeSnippet $snippet,
    ): void {
        $this->getFactory()
            ->createCommandExceptionWriter()
            ->writeLocatedException($output, $locatedException, $snippet);
    }

    public function writeStackTrace(OutputInterface $output, Throwable $e): void
    {
        $this->getFactory()
            ->createCommandExceptionWriter()
            ->writeStackTrace($output, $e);
    }

    public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string
    {
        return $this->getFactory()
            ->createCommandExceptionWriter()
            ->getExceptionString($e, $codeSnippet);
    }

    public function getStackTraceString(Throwable $e): string
    {
        return $this->getFactory()
            ->createCommandExceptionWriter()
            ->getStackTraceString($e);
    }

    /**
     * We want to expose the ExceptionPrinter to `src/phel/test.phel` to be able to print the stack trace.
     */
    public function getExceptionPrinter(): ExceptionPrinterInterface
    {
        return $this->getFactory()->createExceptionPrinter();
    }

    /**
     * All src, tests, and vendor directories.
     *
     * @return list<string>
     */
    public function getAllPhelDirectories(): array
    {
        return [
            ...$this->getSourceDirectories(),
            ...$this->getTestDirectories(),
            ...$this->getVendorSourceDirectories(),
        ];
    }

    public function getSourceDirectories(): array
    {
        return $this->getFactory()
            ->createDirectoryFinder()
            ->getSourceDirectories();
    }

    public function getTestDirectories(): array
    {
        return $this->getFactory()
            ->createDirectoryFinder()
            ->getTestDirectories();
    }

    public function getVendorSourceDirectories(): array
    {
        return $this->getFactory()
            ->createDirectoryFinder()
            ->getVendorSourceDirectories();
    }

    public function getOutputDirectory(): string
    {
        return $this->getFactory()
            ->createDirectoryFinder()
            ->getOutputDirectory();
    }

    public function readPhelConfig(string $absolutePath): array
    {
        return $this->getFactory()
            ->getPhpConfigReader()
            ->read($absolutePath);
    }
}
