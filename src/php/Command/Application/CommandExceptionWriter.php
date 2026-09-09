<?php

declare(strict_types=1);

namespace Phel\Command\Application;

use Phel\Command\Domain\CommandExceptionWriterInterface;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Delegates exception rendering to the {@see ExceptionPrinterInterface} and persists the
 * full stack trace via the {@see ErrorLogInterface}, unwrapping compiled PHP locations
 * back to the originating Phel source for user-facing errors.
 *
 * @internal
 */
final readonly class CommandExceptionWriter implements CommandExceptionWriterInterface
{
    public function __construct(
        private ExceptionPrinterInterface $exceptionPrinter,
        private ErrorLogInterface $errorLog,
        private ExceptionHintResolver $hintResolver,
        private RuntimeErrorReportFormatter $reportFormatter,
    ) {}

    public function writeStackTrace(OutputInterface $output, Throwable $e, bool $showInternalFrames = false): void
    {
        $output->writeln($this->getRuntimeErrorReport($e, $showInternalFrames));
        $this->logStackTrace($e);
    }

    public function getRuntimeErrorReport(Throwable $e, bool $showInternalFrames = false): string
    {
        return $this->reportFormatter->format($e, $showInternalFrames);
    }

    public function writeLocatedException(
        OutputInterface $output,
        AbstractLocatedException $e,
        CodeSnippet $codeSnippet,
    ): void {
        $output->writeln($this->getExceptionString($e, $codeSnippet));

        $hint = $this->hintResolver->hintFor($e);
        if ($hint !== null) {
            $output->writeln('hint: ' . $hint);
        }
    }

    public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string
    {
        return $this->exceptionPrinter->getExceptionString($e, $codeSnippet);
    }

    public function getStackTraceString(Throwable $e): string
    {
        return $this->exceptionPrinter->getStackTraceString($e);
    }

    /**
     * The collapsed-trace marker promises the log holds the whole trace, so
     * every reporting path has to feed it, not only `writeStackTrace()`.
     */
    public function logStackTrace(Throwable $e): void
    {
        $this->errorLog->writeln($this->getStackTraceString($e));
    }
}
