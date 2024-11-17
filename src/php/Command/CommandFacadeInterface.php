<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
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
}
