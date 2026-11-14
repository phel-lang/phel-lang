<?php

declare(strict_types=1);

namespace Phel\Watch\Application\Watcher;

use Phel\Watch\Domain\ClockInterface;
use Phel\Watch\Domain\FileSystemScannerInterface;
use Phel\Watch\Domain\FileWatcherInterface;
use Phel\Watch\Transfer\WatchEvent;

use function array_pop;
use function escapeshellarg;
use function explode;
use function fclose;
use function feof;
use function fgets;
use function fread;
use function is_resource;
use function pclose;
use function popen;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function stream_set_blocking;
use function trim;

/**
 * Shared plumbing for watchers that shell out to an external binary
 * (fswatch, inotifywait) and stream one event record per separator on
 * stdout: a newline by default, `recordSeparator()` for a tool that frames
 * records another way.
 *
 * Concrete subclasses only supply:
 *  - `buildCommand(array $paths): string`
 *  - `parseLine(string $line): ?WatchEvent`
 *  - `name(): string`
 *
 * Everything else — proc lifecycle, non-blocking reads, debounced flushing,
 * graceful stop, PATH probing — lives here so bugs stay fixed in one place.
 *
 * @internal
 */
abstract class AbstractShellWatcher implements FileWatcherInterface
{
    private const int IDLE_SLEEP_MS = 50;

    /** @var resource|null */
    protected mixed $process = null;

    /** @var resource|null */
    protected mixed $stdout = null;

    /** @var array<int, resource> */
    protected array $pipes = [];

    private bool $running = false;

    private string $buffer = '';

    /**
     * @param FileSystemScannerInterface $scanner    primes the mtime snapshot
     *                                               before streaming starts so
     *                                               diffing resolvers have state
     * @param ClockInterface             $clock      drives idle sleeps and
     *                                               debounce timing
     * @param string                     $binaryPath external tool to invoke
     *                                               (e.g. fswatch, inotifywait)
     * @param int                        $debounceMs window for coalescing rapid
     *                                               events into one batch
     */
    public function __construct(
        protected readonly FileSystemScannerInterface $scanner,
        protected readonly ClockInterface $clock,
        protected readonly string $binaryPath,
        protected readonly int $debounceMs = 100,
    ) {}

    /**
     * @param list<string>                    $paths
     * @param callable(list<WatchEvent>):void $onChange
     */
    final public function watch(array $paths, callable $onChange): void
    {
        $this->running = true;
        $this->buffer = '';

        $process = @proc_open(
            $this->buildCommand($paths),
            // The mode is the child's side: it writes stdout. Its stderr goes
            // straight to ours, so a startup error shows and never fills a pipe.
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', 'php://stderr', 'w']],
            $this->pipes,
        );
        if (!is_resource($process)) {
            return;
        }

        $this->process = $process;
        $this->stdout = $this->pipes[1];
        stream_set_blocking($this->stdout, false);

        // Prime the snapshot so resolvers that diff mtime still have state.
        $this->scanner->snapshot($paths);

        $debouncer = new EventDebouncer($this->clock, $this->debounceMs);

        /** @psalm-suppress RedundantCondition */
        while ($this->running && $this->isAlive()) {
            // phpstan sees $stdout as resource|null and needs the guard before
            // fgets; psalm narrows it to non-null here and flags the check.
            /** @psalm-suppress TypeDoesNotContainNull */
            if ($this->stdout === null) {
                break;
            }

            $this->readAvailable($this->stdout, $debouncer);

            if ($debouncer->hasPending()) {
                $debouncer->flushIfReady($onChange);
            } else {
                $this->clock->sleepMs(self::IDLE_SLEEP_MS);
            }
        }

        // The tool may exit right after its last lines: read what it left and
        // deliver what is still waiting instead of dropping it.
        $stdout = $this->stdout;
        /** @psalm-suppress RedundantCondition `stop()` clears both from a signal handler */
        if ($this->running && is_resource($stdout)) {
            stream_set_blocking($stdout, true);
            while (!feof($stdout) && $this->readAvailable($stdout, $debouncer)) {
                // keep reading until the tool's output ends
            }

            if ($this->buffer !== '') {
                $this->record($this->buffer, $debouncer);
            }

            $debouncer->flushIfReady($onChange, true);
        }

        $this->close();
    }

    final public function stop(): void
    {
        $this->running = false;
        $this->close();
    }

    final protected static function binaryIsOnPath(string $binary): bool
    {
        $handle = @popen('command -v ' . escapeshellarg($binary) . ' 2>/dev/null', 'r');
        if (!is_resource($handle)) {
            return false;
        }

        $out = (string) fgets($handle);
        pclose($handle);

        return trim($out) !== '';
    }

    /**
     * @param list<string> $paths
     */
    abstract protected function buildCommand(array $paths): string;

    abstract protected function parseLine(string $line): ?WatchEvent;

    /**
     * @return non-empty-string
     */
    protected function recordSeparator(): string
    {
        return "\n";
    }

    /**
     * Reads what the tool has written so far and records each complete
     * record. A partial record stays buffered: a non-blocking read can stop
     * anywhere, even inside a path.
     *
     * @param resource $stream
     *
     * @return bool false once nothing more could be read
     */
    private function readAvailable(mixed $stream, EventDebouncer $debouncer): bool
    {
        $chunk = @fread($stream, 8192);
        if ($chunk === false || $chunk === '') {
            return false;
        }

        $records = explode($this->recordSeparator(), $this->buffer . $chunk);
        $this->buffer = array_pop($records);
        foreach ($records as $record) {
            $this->record($record, $debouncer);
        }

        return true;
    }

    private function record(string $line, EventDebouncer $debouncer): void
    {
        $event = $this->parseLine($line);
        if ($event instanceof WatchEvent) {
            $debouncer->record($event);
        }
    }

    /**
     * @phpstan-impure
     */
    private function isAlive(): bool
    {
        if (!is_resource($this->process)) {
            return false;
        }

        $status = @proc_get_status($this->process);
        return $status['running'];
    }

    private function close(): void
    {
        foreach ($this->pipes as $pipe) {
            /** @psalm-suppress RedundantConditionGivenDocblockType */
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }

        $this->pipes = [];
        $this->stdout = null;

        if (is_resource($this->process)) {
            @proc_terminate($this->process);
            @proc_close($this->process);
        }

        $this->process = null;
    }
}
