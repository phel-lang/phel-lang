<?php

declare(strict_types=1);

namespace Phel\Command\Application;

use Phel\Command\Domain\CommandExceptionWriterInterface;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Command\Domain\Exceptions\Extractor\ReadModel\FilePosition;
use Phel\Command\Domain\Exceptions\InternalPathDetector;
use Phel\Lang\ExceptionInfo;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Phel\Shared\Printer\PrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function count;
use function is_string;
use function sprintf;
use function str_ends_with;

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
        private FilePositionExtractorInterface $filePositionExtractor,
        private string $staleOutputHint,
        private ExceptionHintResolver $hintResolver,
        private InternalPathDetector $internalPathDetector,
        private PrinterInterface $printer,
    ) {}

    public function writeStackTrace(OutputInterface $output, Throwable $e, bool $showInternalFrames = false): void
    {
        $cause = $e->getPrevious() ?? $e;

        // When the throw site is the runtime lib (e.g. core `+`, `/`), its file
        // is internal and maps to no user source, so the located `at` header is
        // omitted; the Phel call sites still surface via the filtered trace.
        if (str_contains($cause->getFile(), 'phel-lang/src')) {
            $output->writeln($cause->getMessage());
        } else {
            $this->writeUserError($output, $cause);
        }

        $this->writeExceptionData($output, $e);

        $trace = $this->exceptionPrinter->getUserFacingTraceString($cause, $showInternalFrames);
        if ($trace !== '') {
            $output->writeln(rtrim($trace));
        }

        $this->writeHint($output, $e);
        $this->logStackTrace($e);
    }

    public function writeLocatedException(
        OutputInterface $output,
        AbstractLocatedException $e,
        CodeSnippet $codeSnippet,
    ): void {
        $output->writeln($this->getExceptionString($e, $codeSnippet));
        $this->writeHint($output, $e);
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

    /**
     * Appends an actionable hint (undefined symbol, wrong arity, not callable,
     * ...) when one applies, matching the guidance the REPL shows.
     */
    private function writeHint(OutputInterface $output, Throwable $e): void
    {
        $hint = $this->hintResolver->hintFor($e);
        if ($hint !== null) {
            $output->writeln('hint: ' . $hint);
        }
    }

    /**
     * Prints the data map carried by an `ex-info`, which otherwise has to be
     * inferred from the arguments of an `ex-info` frame in the trace.
     */
    private function writeExceptionData(OutputInterface $output, Throwable $e): void
    {
        for ($current = $e; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof ExceptionInfo && count($current->getData()) > 0) {
                $output->writeln('  data: ' . $this->printer->print($current->getData()));
                return;
            }
        }
    }

    /**
     * Renders a user-facing error at its anchor frame, mapping the compiled PHP
     * file/line back to its original Phel source via the source map when available.
     *
     * When the source map resolves to a different file, the original location is
     * shown alongside the compiled one. Otherwise only the raw location is shown,
     * and a stale-output hint is appended when the failing file is generated PHP
     * (`.php`), since that usually signals an out-of-date build.
     */
    private function writeUserError(OutputInterface $output, Throwable $cause): void
    {
        [$position, $file, $line] = $this->anchorFrame($cause);

        $output->writeln($cause->getMessage());

        if ($position->filename() !== $file) {
            // An internal artifact (the eval temp file, the compiled cache) has
            // no meaning to a Phel user, so only show the resolved source. A
            // persistent build artifact (`out/phel/…`) still names its compiled
            // file, which is useful when diagnosing a stale build.
            if ($this->internalPathDetector->isInternalArtifact($file)) {
                $output->writeln(sprintf('  at %s:%d', $position->filename(), $position->line()));
                return;
            }

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

    /**
     * The frame the `at` line points at: the throw site when it belongs to the
     * user's project, otherwise the innermost Phel frame that does. An error
     * raised inside the core library names the caller rather than the core
     * source, which is no place for the user to act on (#3260).
     *
     * @return array{FilePosition, string, int} the resolved position, plus the
     *                                          compiled file and line it came from
     */
    private function anchorFrame(Throwable $cause): array
    {
        $file = $cause->getFile();
        $line = $cause->getLine();
        $position = $this->filePositionExtractor->getOriginal($file, $line);

        if (!$this->isInternalPosition($position)) {
            return [$position, $file, $line];
        }

        foreach ($cause->getTrace() as $frame) {
            $frameFile = $frame['file'] ?? null;
            if (!is_string($frameFile)) {
                continue;
            }

            $frameLine = $frame['line'] ?? 0;
            $framePosition = $this->filePositionExtractor->getOriginal($frameFile, $frameLine);

            // Only a frame mapping back to Phel source can anchor the `at`
            // line: vendor and runtime PHP frames are not the user's call site.
            if (str_ends_with($framePosition->filename(), '.phel') && !$this->isInternalPosition($framePosition)) {
                return [$framePosition, $frameFile, $frameLine];
            }
        }

        return [$position, $file, $line];
    }

    private function isInternalPosition(FilePosition $position): bool
    {
        return $this->internalPathDetector->isInternalSource($position->filename());
    }
}
