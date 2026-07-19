<?php

declare(strict_types=1);

namespace Phel\Command\Application;

use Phel\Command\Domain\CommandExceptionWriterInterface;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;
use function str_ends_with;

/**
 * Delegates exception rendering to the {@see ExceptionPrinterInterface} and persists the
 * full stack trace via the {@see ErrorLogInterface}, unwrapping compiled PHP locations
 * back to the originating Phel source for user-facing errors.
 */
final readonly class CommandExceptionWriter implements CommandExceptionWriterInterface
{
    public function __construct(
        private ExceptionPrinterInterface $exceptionPrinter,
        private ErrorLogInterface $errorLog,
        private FilePositionExtractorInterface $filePositionExtractor,
        private string $staleOutputHint,
        private ExceptionHintResolver $hintResolver,
    ) {}

    public function writeStackTrace(OutputInterface $output, Throwable $e): void
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

        $trace = $this->exceptionPrinter->getUserFacingTraceString($cause);
        if ($trace !== '') {
            $output->writeln(rtrim($trace));
        }

        $this->writeHint($output, $e);
        $this->errorLog->writeln($this->getStackTraceString($e));
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
     * Renders a user-facing error, mapping the compiled PHP file/line back to its
     * original Phel source via the source map when available.
     *
     * When the source map resolves to a different file, the original location is
     * shown alongside the compiled one. Otherwise only the raw location is shown,
     * and a stale-output hint is appended when the failing file is generated PHP
     * (`.php`), since that usually signals an out-of-date build.
     */
    private function writeUserError(OutputInterface $output, Throwable $cause): void
    {
        $file = $cause->getFile();
        $line = $cause->getLine();
        $position = $this->filePositionExtractor->getOriginal($file, $line);

        $output->writeln($cause->getMessage());

        if ($position->filename() !== $file) {
            // An ephemeral eval temp file (`__phel_<hash>.php`) is an internal
            // artifact with no meaning to a Phel user, so only show the resolved
            // source. A persistent build artifact (`out/phel/…`) still names its
            // compiled file, which is useful when diagnosing a stale build.
            if ($this->isEphemeralCompiledFile($file)) {
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
     * Whether the compiled file is an ephemeral eval temp file (named
     * `__phel_<hash>.php` by the evaluator), as opposed to a persistent build
     * artifact under `out/`.
     */
    private function isEphemeralCompiledFile(string $file): bool
    {
        return str_starts_with(basename($file), '__phel');
    }
}
