<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\Domain\Repl\ReplErrorFormatter;
use Phel\Shared\ColorStyle;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\Hint\ArgumentCountHint;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Exceptions\Hint\NotCallableHint;
use Phel\Shared\Exceptions\Hint\UndefinedSymbolHint;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Throwable;

use function array_slice;
use function count;
use function ob_clean;
use function ob_end_clean;
use function ob_get_contents;
use function ob_get_level;
use function ob_start;

final class ReplTestIo implements ReplCommandIoInterface
{
    /** @var list<string> */
    private array $outputs = [];

    /** @var list<InputLine> */
    private array $inputs = [];

    private int $currentIndex = 0;

    /**
     * Output-buffer nesting level this IO owns, or 0 when not capturing.
     *
     * The real REPL writes results and `println`/`doc` output to one stdout
     * stream, so they interleave. This double buffers results in {@see $outputs}
     * instead, so `println` (hard-coded `php/print`) would otherwise land on a
     * separate real stdout and be lost or reordered. Owning a dedicated `ob_*`
     * level and draining it into {@see $outputs} on every write restores the
     * real interleaving while keeping the array-based accessors intact.
     */
    private int $captureLevel = 0;

    private readonly ReplErrorFormatter $errorFormatter;

    public function __construct(
        private readonly ExceptionPrinterInterface $exceptionPrinter,
        ?ReplErrorFormatter $errorFormatter = null,
    ) {
        $this->errorFormatter = $errorFormatter ?? new ReplErrorFormatter(
            new ExceptionHintResolver([
                new NotCallableHint(),
                new ArgumentCountHint(),
                new UndefinedSymbolHint(),
            ]),
            $exceptionPrinter,
            ColorStyle::noStyles(),
        );
    }

    public function readHistory(): void {}

    public function addHistory(string $line): void {}

    public function readline(?string $prompt = null): ?string
    {
        // Begin capturing before the first eval so any `println`/`doc` output
        // it produces is interleaved with the results, not sent to real stdout.
        $this->startCapture();

        if ($this->currentIndex < count($this->inputs)) {
            $inputLine = $this->inputs[$this->currentIndex];
            $this->writeln($inputLine->__toString());
            ++$this->currentIndex;

            if ($inputLine->isCtrlD()) {
                return null;
            }

            return $inputLine->getContent();
        }

        return null;
    }

    public function writeStackTrace(Throwable $e): void
    {
        $this->write($this->exceptionPrinter->getStackTraceString($e));
    }

    public function writeReplError(Throwable $e): void
    {
        $this->write($this->errorFormatter->render($e));
    }

    public function writeLocatedException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void
    {
        $this->write($this->exceptionPrinter->getExceptionString($e, $codeSnippet));
    }

    public function write(string $string = ''): void
    {
        $this->drainCapture();
        $this->outputs[] = $string;
    }

    /**
     * Terminates the line, like the real IO does. Without this the captured
     * transcript runs an evaluated result straight into the next prompt
     * (`3user:2> *1`), which is why the `.test` fixtures could never match.
     */
    public function writeln(string $string = ''): void
    {
        $this->drainCapture();
        $this->outputs[] = $string . PHP_EOL;
    }

    public function setInputs(InputLine ...$inputs): void
    {
        $this->inputs = $inputs;
        $this->currentIndex = 0;
    }

    /**
     * @return list<string>
     */
    public function getOutputs(): array
    {
        $this->finishCapture();

        return array_slice($this->outputs, 2, -1);
    }

    public function getOutputString(): string
    {
        return implode('', $this->getOutputs()) . PHP_EOL;
    }

    /**
     * @return list<string>
     */
    public function getRawOutputs(): array
    {
        $this->finishCapture();

        return $this->outputs;
    }

    /**
     * Raw outputs with the line terminator stripped, for assertions that care
     * about the logical line and not its framing. Prefer this over
     * {@see self::getRawOutputs()} when matching a whole entry, so the
     * assertion does not have to spell out `. PHP_EOL`.
     *
     * @return list<string>
     */
    public function getOutputLines(): array
    {
        $this->finishCapture();

        return array_map(
            static fn(string $output): string => rtrim($output, PHP_EOL),
            $this->outputs,
        );
    }

    public function isBracketedPasteSupported(): bool
    {
        return false;
    }

    /**
     * Open a dedicated output buffer the first time the REPL produces output.
     * Its level is recorded so {@see drainCapture()}/{@see finishCapture()}
     * only ever touch our own buffer, never PHPUnit's or a surrounding test's.
     */
    private function startCapture(): void
    {
        if ($this->captureLevel === 0) {
            ob_start();
            $this->captureLevel = ob_get_level();
        }
    }

    /**
     * Move whatever `println`/`doc` wrote to real stdout since the last write
     * into {@see $outputs}, in place, so it interleaves with the results.
     */
    private function drainCapture(): void
    {
        if ($this->captureLevel === 0 || ob_get_level() !== $this->captureLevel) {
            return;
        }

        $pending = ob_get_contents();
        if ($pending !== false && $pending !== '') {
            ob_clean();
            $this->outputs[] = $pending;
        }
    }

    /**
     * Drain any trailing stdout and close our buffer. Idempotent, and safe to
     * call from every accessor so reads always see the complete transcript.
     */
    private function finishCapture(): void
    {
        if ($this->captureLevel === 0) {
            return;
        }

        if (ob_get_level() === $this->captureLevel) {
            $pending = ob_get_contents();
            if ($pending !== false && $pending !== '') {
                $this->outputs[] = $pending;
            }

            ob_end_clean();
        }

        $this->captureLevel = 0;
    }
}
