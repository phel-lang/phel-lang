<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

interface CommandFacadeInterface
{
    public function writeLocatedException(
        OutputInterface $output,
        AbstractLocatedException $locatedException,
        CodeSnippet $snippet
    ): void;

    public function writeStackTrace(OutputInterface $output, Throwable $e): void;

    public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string;

    public function getStackTraceString(Throwable $e): string;

    public function registerExceptionHandler(): void;

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

    public function getOutputDirectory(): string;
}
