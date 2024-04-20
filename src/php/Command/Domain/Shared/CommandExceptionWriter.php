<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Shared;

use Phel\Command\Domain\Shared\ErrorLog\ErrorLogInterface;
use Phel\Command\Domain\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Transpiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Transpiler\Domain\Parser\ReadModel\CodeSnippet;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final readonly class CommandExceptionWriter implements CommandExceptionWriterInterface
{
    public function __construct(
        private ExceptionPrinterInterface $exceptionPrinter,
        private ErrorLogInterface $errorLog,
    ) {
    }

    public function writeStackTrace(
        OutputInterface $output,
        Throwable $e,
    ): void {
        $output->writeln($e->getMessage());

        $this->errorLog->writeln($this->getStackTraceString($e));
    }

    public function writeLocatedException(
        OutputInterface $output,
        AbstractLocatedException $e,
        CodeSnippet $codeSnippet,
    ): void {
        $output->writeln($this->getExceptionString($e, $codeSnippet));
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
