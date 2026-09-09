<?php

declare(strict_types=1);

namespace Phel\Command\Domain;

use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @internal
 */
interface CommandExceptionWriterInterface
{
    public function writeStackTrace(OutputInterface $output, Throwable $e, bool $showInternalFrames = false): void;

    public function writeLocatedException(
        OutputInterface $output,
        AbstractLocatedException $e,
        CodeSnippet $codeSnippet,
    ): void;

    public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string;

    public function getStackTraceString(Throwable $e): string;

    public function logStackTrace(Throwable $e): void;
}
