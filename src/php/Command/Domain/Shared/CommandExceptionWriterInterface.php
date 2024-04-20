<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Shared;

use Phel\Transpiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Transpiler\Domain\Parser\ReadModel\CodeSnippet;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

interface CommandExceptionWriterInterface
{
    public function writeStackTrace(OutputInterface $output, Throwable $e): void;

    public function writeLocatedException(
        OutputInterface $output,
        AbstractLocatedException $e,
        CodeSnippet $codeSnippet,
    ): void;

    public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string;

    public function getStackTraceString(Throwable $e): string;
}
