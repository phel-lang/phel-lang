<?php

declare(strict_types=1);

namespace Phel\Shared\Facade;

use Phel\Command\Domain\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

interface CommandFacadeInterface
{
    public function writeLocatedException(
        OutputInterface $output,
        AbstractLocatedException $locatedException,
        CodeSnippet $snippet,
    ): void;

    public function writeStackTrace(OutputInterface $output, Throwable $e): void;

    public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string;

    public function getStackTraceString(Throwable $e): string;

    public function getExceptionPrinter(): ExceptionPrinterInterface;

    public function getExceptionHintResolver(): ExceptionHintResolver;

    /**
     * All src, tests, and vendor directories.
     *
     * @return list<string>
     */
    public function getAllPhelDirectories(): array;

    /**
     * @return list<string>
     */
    public function getSourceDirectories(): array;

    /**
     * Source directories configured by the user — excludes phel's own
     * bundled stdlib directory that is prepended for runtime namespace
     * resolution.
     *
     * @return list<string>
     */
    public function getProjectSourceDirectories(): array;

    /**
     * @return list<string>
     */
    public function getTestDirectories(): array;

    /**
     * @return list<string>
     */
    public function getVendorSourceDirectories(): array;

    /**
     * Relative path to the output directory result of the build command.
     */
    public function getOutputDirectory(): string;

    /**
     * @return array<string,mixed>
     */
    public function readPhelConfig(string $absolutePath): array;

    /**
     * Maps every mapped generated line of a compiled PHP file back to its Phel
     * source, for coverage reporting. Empty filename when no source map.
     *
     * @return array{filename: string, lines: array<int, int>}
     */
    public function getCompiledFileLineMap(string $compiledFile): array;
}
