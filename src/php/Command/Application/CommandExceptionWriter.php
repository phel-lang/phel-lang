<?php

declare(strict_types=1);

namespace Phel\Command\Application;

use Phel\Command\Domain\CommandExceptionWriterInterface;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionPrinterInterface;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

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
        $message = $e->getPrevious()?->getMessage() ?? $e->getMessage();
        $file = $e->getPrevious()?->getFile() ?? $e->getFile();

        if (!str_contains($file, 'phel-lang/src')) {
            $output->writeln(sprintf('%s // file: %s', $message, $file));
            $output->writeln('> Dont you see the file? Check your phel config got `KeepGeneratedTempFiles=true`');
        } else {
            $output->writeln($message);
        }

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
