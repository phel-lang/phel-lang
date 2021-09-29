<?php

declare(strict_types=1);

namespace Phel\Command\Shared;

use Phel\Command\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class CommandExceptionWriter implements CommandExceptionWriterInterface
{
    private ExceptionPrinterInterface $exceptionPrinter;

    public function __construct(ExceptionPrinterInterface $exceptionPrinter)
    {
        $this->exceptionPrinter = $exceptionPrinter;
    }

    public function writeStackTrace(
        OutputInterface $output,
        Throwable $e
    ): void {
        $output->writeln($this->exceptionPrinter->getStackTraceString($e));

        if ($e->getPrevious()) {
            $output->writeln('');
            $output->writeln('Caused by');
            $this->writeStackTrace($output, $e->getPrevious());
        }
    }

    public function writeLocatedException(
        OutputInterface $output,
        AbstractLocatedException $e,
        CodeSnippet $codeSnippet
    ): void {
        $output->writeln($this->exceptionPrinter->getExceptionString($e, $codeSnippet));
    }

    public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string
    {
        return $this->exceptionPrinter->getExceptionString($e, $codeSnippet);
    }

    public function getStackTraceString(Throwable $e): string
    {
        return $this->exceptionPrinter->getStackTraceString($e);
    }
}
