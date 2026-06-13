<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Attribute\Cacheable;
use Phel\Command\Domain\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @extends AbstractFacade<CommandFactory>
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

    public function getExceptionPrinter(): ExceptionPrinterInterface
    {
        return $this->getFactory()->createExceptionPrinter();
    }

    public function getCompiledFileLineMap(string $compiledFile): array
    {
        return $this->getFactory()
            ->createFilePositionExtractor()
            ->getFileLineMap($compiledFile);
    }

    /**
     * @return list<string>
     */
    #[Cacheable]
    public function getAllPhelDirectories(): array
    {
        return $this->cached(fn(): array => $this->getFactory()
            ->createDirectoryFinder()
            ->getAllPhelDirectories());
    }

    #[Cacheable]
    public function getSourceDirectories(): array
    {
        return $this->cached(fn(): array => $this->getFactory()
            ->createDirectoryFinder()
            ->getSourceDirectories());
    }

    #[Cacheable]
    public function getProjectSourceDirectories(): array
    {
        return $this->cached(fn(): array => $this->getFactory()
            ->createDirectoryFinder()
            ->getProjectSourceDirectories());
    }

    #[Cacheable]
    public function getTestDirectories(): array
    {
        return $this->cached(fn(): array => $this->getFactory()
            ->createDirectoryFinder()
            ->getTestDirectories());
    }

    #[Cacheable]
    public function getVendorSourceDirectories(): array
    {
        return $this->cached(fn(): array => $this->getFactory()
            ->createDirectoryFinder()
            ->getVendorSourceDirectories());
    }

    #[Cacheable]
    public function getOutputDirectory(): string
    {
        return $this->cached(fn(): string => $this->getFactory()
            ->createDirectoryFinder()
            ->getOutputDirectory());
    }

    public function readPhelConfig(string $absolutePath): array
    {
        return $this->getFactory()
            ->getPhpConfigReader()
            ->read($absolutePath);
    }
}
