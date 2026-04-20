<?php

declare(strict_types=1);

namespace Phel\Watch\Application\Watcher;

use Phel\Watch\Domain\ClockInterface;
use Phel\Watch\Domain\FileSystemScannerInterface;
use Phel\Watch\Domain\FileWatcherInterface;
use Phel\Watch\Transfer\WatchEvent;

use function escapeshellarg;
use function fclose;
use function fgets;
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
 * (fswatch, inotifywait) and stream events line-by-line on stdout.
 *
 * Concrete subclasses only supply:
 *  - `buildCommand(array $paths): string`
 *  - `parseLine(string $line): ?WatchEvent`
 *  - `name(): string`
 *
 * Everything else — proc lifecycle, non-blocking reads, debounced flushing,
 * graceful stop, PATH probing — lives here so bugs stay fixed in one place.
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

        $process = @proc_open(
            $this->buildCommand($paths),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'r'], 2 => ['pipe', 'r']],
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
            $line = @fgets($this->stdout);
            if ($line !== false) {
                $event = $this->parseLine($line);
                if ($event instanceof WatchEvent) {
                    $debouncer->record($event);
                }
            }

            if ($debouncer->hasPending()) {
                $debouncer->flushIfReady($onChange);
            } else {
                $this->clock->sleepMs(self::IDLE_SLEEP_MS);
            }
        }

        $this->close();
    }

    final public function stop(): void
    {
        $this->running = false;
        $this->close();
    }

    /**
     * Detect whether the external binary is on PATH.
     */
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
