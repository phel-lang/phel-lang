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
        private string $staleOutputHint,
    ) {}

    public function writeStackTrace(OutputInterface $output, Throwable $e): void
    {
        $cause = $e->getPrevious() ?? $e;

        if (str_contains($cause->getFile(), 'phel-lang/src')) {
            $output->writeln($cause->getMessage());
        } else {
            $this->writeUserError($output, $cause);
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

    private function writeUserError(OutputInterface $output, Throwable $cause): void
    {
        $file = $cause->getFile();
        $line = $cause->getLine();
        $position = $this->filePositionExtractor->getOriginal($file, $line);

        $output->writeln($cause->getMessage());

        if ($position->filename() !== $file) {
            $output->writeln(sprintf(
                '  at %s:%d (compiled: %s:%d)',
                $position->filename(),
                $position->line(),
                $file,
                $line,
            ));
            return;
        }

        $output->writeln(sprintf('  at %s:%d', $file, $line));
        if (str_ends_with($file, '.php')) {
            $output->writeln('  hint: ' . $this->staleOutputHint);
        }
    }
}
