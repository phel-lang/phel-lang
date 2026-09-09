<?php

declare(strict_types=1);

namespace Phel\Command\Application;

use Phel\Command\Domain\Exceptions\AnchorFrame;
use Phel\Command\Domain\Exceptions\EvaluatedCodeLocation;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Command\Domain\Exceptions\Extractor\ReadModel\FilePosition;
use Phel\Command\Domain\Exceptions\InternalPathDetector;
use Phel\Lang\ExceptionInfo;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Printer\PrinterInterface;
use Throwable;

use function count;
use function implode;
use function is_string;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function trim;

use const PHP_EOL;

/**
 * The one report every uncaught runtime error gets, whichever command reports
 * it (`phel run`, `phel eval`, the REPL):
 *
 *     <message>
 *       at <the innermost frame of the user's own code>
 *       data: <the map of an uncaught ex-info>
 *     <the user-visible frames>
 *        ... N internal frames (<the way out>)
 *     hint: <when one matches>
 *
 * Every section but the message and the `at` line is optional, and the order
 * never changes: three commands used to print three layouts of the same
 * failure, so a reader could not learn one shape (#3264).
 *
 * @internal
 */
final readonly class RuntimeErrorReportFormatter
{
    public function __construct(
        private ExceptionPrinterInterface $exceptionPrinter,
        private FilePositionExtractorInterface $filePositionExtractor,
        private InternalPathDetector $internalPathDetector,
        private ExceptionHintResolver $hintResolver,
        private PrinterInterface $printer,
        private string $staleOutputHint,
    ) {}

    public function format(Throwable $e, bool $showInternalFrames = false): string
    {
        $cause = $e->getPrevious() ?? $e;
        $anchor = $this->anchorFrame($cause);

        $sections = [
            $this->messageLine($cause),
            $this->anchorLine($anchor),
        ];

        $data = $this->dataLine($e);
        if ($data !== null) {
            $sections[] = $data;
        }

        $trace = rtrim($this->exceptionPrinter->getUserFacingTraceString($cause, $showInternalFrames));
        if ($trace !== '') {
            $sections[] = $trace;
        }

        $hint = $this->hint($e, $anchor);
        if ($hint !== null) {
            $sections[] = 'hint: ' . $hint;
        }

        return implode(PHP_EOL, $sections);
    }

    private function messageLine(Throwable $cause): string
    {
        $message = $cause->getMessage();
        if ($message === '') {
            return '*no message*';
        }

        // PHP names the evaluator's temp path and an eval line number in the
        // messages it raises itself, neither of which the user can open.
        $cleaned = preg_replace('/ in [^\s]+\(\d+\)\s*:\s*eval\(\)\'d code on line \d+/', '', $message);

        return trim($cleaned ?? $message);
    }

    /**
     * Names the location to go and look at, mapping the compiled PHP file and
     * line back to their Phel source through the source map when there is one.
     *
     * When the source map resolves to a different file, the original location
     * is shown alongside the compiled one, unless that compiled file is an
     * internal artifact (the eval temp file, the compiled cache), which means
     * nothing to a Phel user. A persistent build artifact under `out/` still
     * names its compiled file, which is what diagnosing a stale build needs.
     */
    private function anchorLine(AnchorFrame $anchor): string
    {
        if ($anchor->isReplInput()) {
            return '  at ' . EvaluatedCodeLocation::LABEL;
        }

        if (!$anchor->isMapped()) {
            return sprintf('  at %s:%d', $anchor->compiledFile, $anchor->compiledLine);
        }

        if ($this->internalPathDetector->isInternalArtifact($anchor->compiledFile)) {
            return sprintf('  at %s:%d', $anchor->position->filename(), $anchor->position->line());
        }

        return sprintf(
            '  at %s:%d (compiled: %s:%d)',
            $anchor->position->filename(),
            $anchor->position->line(),
            $anchor->compiledFile,
            $anchor->compiledLine,
        );
    }

    /**
     * The frame the `at` line points at: the throw site when it belongs to the
     * user, otherwise the innermost frame that does, be it a file of their own
     * project or what they typed at the prompt. An error raised inside the core
     * library names the caller rather than the core source, which is no place
     * for the user to act on (#3260). With no frame of the user's own, the
     * innermost Phel frame stands in, and the throw site is the last resort.
     */
    private function anchorFrame(Throwable $cause): AnchorFrame
    {
        $file = $cause->getFile();
        $line = $cause->getLine();
        $position = $this->filePositionExtractor->getOriginal($file, $line);

        if (!$this->isInternalPosition($position)) {
            return new AnchorFrame($position, $file, $line);
        }

        $innermostPhelFrame = null;

        foreach ($cause->getTrace() as $frame) {
            $frameFile = $frame['file'] ?? null;
            if (!is_string($frameFile)) {
                continue;
            }

            $frameLine = $frame['line'] ?? 0;
            $framePosition = $this->filePositionExtractor->getOriginal($frameFile, $frameLine);

            if ($this->isUserCode($framePosition)) {
                return new AnchorFrame($framePosition, $frameFile, $frameLine);
            }

            if (!$innermostPhelFrame instanceof AnchorFrame && str_ends_with($framePosition->filename(), '.phel')) {
                $innermostPhelFrame = new AnchorFrame($framePosition, $frameFile, $frameLine);
            }
        }

        return $innermostPhelFrame ?? new AnchorFrame($position, $file, $line);
    }

    /**
     * A file of the user's own project, or the prompt they typed the form at:
     * the two places a Phel user can act on.
     */
    private function isUserCode(FilePosition $position): bool
    {
        if (EvaluatedCodeLocation::matches($position->filename())) {
            return true;
        }

        return str_ends_with($position->filename(), '.phel') && !$this->isInternalPosition($position);
    }

    private function isInternalPosition(FilePosition $position): bool
    {
        return $this->internalPathDetector->isInternalSource($position->filename());
    }

    /**
     * Prints the data map carried by an `ex-info`, which otherwise has to be
     * inferred from the arguments of an `ex-info` frame in the trace.
     */
    private function dataLine(Throwable $e): ?string
    {
        for ($current = $e; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof ExceptionInfo && count($current->getData()) > 0) {
                return '  data: ' . $this->printer->print($current->getData());
            }
        }

        return null;
    }

    /**
     * An actionable hint (undefined symbol, wrong arity, not callable, ...)
     * when one applies, and otherwise the stale-output recovery hint when the
     * report anchors on generated PHP with no Phel source left to map it back.
     */
    private function hint(Throwable $e, AnchorFrame $anchor): ?string
    {
        $hint = $this->hintResolver->hintFor($e);
        if ($hint !== null) {
            return $hint;
        }

        $isUnmappedGeneratedPhp = !$anchor->isMapped()
            && str_ends_with($anchor->compiledFile, '.php')
            && !$this->isInternalPosition($anchor->position);

        return $isUnmappedGeneratedPhp ? $this->staleOutputHint : null;
    }
}
