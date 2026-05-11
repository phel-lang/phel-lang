<?php

declare(strict_types=1);

namespace Phel\Command\Application;

use Phel\Command\Domain\CommandExceptionWriterInterface;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionPrinterInterface;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;
use function str_ends_with;

final readonly class CommandExceptionWriter implements CommandExceptionWriterInterface
{
    public function __construct(
        private ExceptionPrinterInterface $exceptionPrinter,
        private ErrorLogInterface $errorLog,
        private FilePositionExtractorInterface $filePositionExtractor,
    ) {}

    public function writeStackTrace(
        OutputInterface $output,
        Throwable $e,
    ): void {
        $cause = $e->getPrevious() ?? $e;
        $message = $cause->getMessage();
        $file = $cause->getFile();
        $line = $cause->getLine();

        if (str_contains($file, 'phel-lang/src')) {
            $output->writeln($message);
            $this->errorLog->writeln($this->getStackTraceString($e));
            return;
        }

        $output->writeln($message);

        if (str_ends_with($file, '.php')) {
            $position = $this->filePositionExtractor->getOriginal($file, $line);
            $resolvedToPhel = $position->filename() !== $file;

            if ($resolvedToPhel) {
                $output->writeln(sprintf(
                    '  at %s:%d (compiled: %s:%d)',
                    $position->filename(),
                    $position->line(),
                    $file,
                    $line,
                ));
            } else {
                $output->writeln(sprintf('  at %s:%d', $file, $line));
                $output->writeln('  hint: stale compiled output? try `rm -rf out/ .phel/cache/` and rebuild.');
            }
        } else {
            $output->writeln(sprintf('  at %s:%d', $file, $line));
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
