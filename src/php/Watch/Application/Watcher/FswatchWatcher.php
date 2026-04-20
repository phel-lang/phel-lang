<?php

declare(strict_types=1);

namespace Phel\Watch\Application\Watcher;

use Phel\Watch\Application\SystemClock;
use Phel\Watch\Domain\ClockInterface;
use Phel\Watch\Domain\FileSystemScannerInterface;
use Phel\Watch\Domain\FileWatcherInterface;
use Phel\Watch\Transfer\WatchEvent;

use function escapeshellarg;
use function fgets;
use function implode;
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
 * macOS-friendly watcher. Pipes events out of the `fswatch` binary and hands
 * them off through the same debounce logic as `PollingWatcher`. Falls back to
 * the polling watcher when `fswatch` is not available.
 */
final class FswatchWatcher implements FileWatcherInterface
{
    public const string NAME = 'fswatch';

    /** @var resource|null */
    private mixed $process = null;

    /** @var resource|null */
    private mixed $stdout = null;

    /** @var resource|null */
    private mixed $stderr = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private bool $running = false;

    public function __construct(
        private readonly FileSystemScannerInterface $scanner,
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly string $binaryPath = 'fswatch',
        private readonly int $debounceMs = 100,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    /**
     * @param list<string>                    $paths
     * @param callable(list<WatchEvent>):void $onChange
     */
    public function watch(array $paths, callable $onChange): void
    {
        $this->running = true;

        $cmd = $this->buildCommand($paths);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'r'],
            2 => ['pipe', 'r'],
        ];

        $process = @proc_open($cmd, $descriptors, $this->pipes);
        if (!is_resource($process)) {
            return;
        }

        $this->process = $process;
        $this->stdout = $this->pipes[1];
        $this->stderr = $this->pipes[2];
        stream_set_blocking($this->stdout, false);
        stream_set_blocking($this->stderr, false);

        // Prime the snapshot so the resolver can mtime-diff if needed.
        $this->scanner->snapshot($paths);

        /** @var list<WatchEvent> $pending */
        $pending = [];
        $lastEventAt = 0;

        /** @psalm-suppress RedundantCondition */
        while ($this->running && $this->isAlive()) {
            $line = @fgets($this->stdout);
            if ($line !== false) {
                $path = trim($line);
                if ($path !== '') {
                    $pending[] = new WatchEvent($path, WatchEvent::KIND_MODIFIED);
                    $lastEventAt = $this->clock->nowMs();
                }
            }

            if ($pending !== [] && $this->clock->nowMs() - $lastEventAt >= $this->debounceMs) {
                $onChange($this->coalesce($pending));
                $pending = [];
            } else {
                $this->clock->sleepMs(50);
            }
        }

        $this->close();
    }

    public function stop(): void
    {
        $this->running = false;
        $this->close();
    }

    /**
     * Detect whether `fswatch` is available on PATH.
     */
    public static function isAvailable(string $binary = 'fswatch'): bool
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
     * @param list<WatchEvent> $events
     *
     * @return list<WatchEvent>
     */
    private function coalesce(array $events): array
    {
        /** @var array<string, WatchEvent> $byPath */
        $byPath = [];
        foreach ($events as $event) {
            $byPath[$event->path] = $event;
        }

        return array_values($byPath);
    }

    /**
     * @param list<string> $paths
     */
    private function buildCommand(array $paths): string
    {
        $args = [];
        foreach ($paths as $path) {
            $args[] = escapeshellarg($path);
        }

        return escapeshellarg($this->binaryPath)
            . ' -r -0 -L -0 --event-flags '
            . '--event Created --event Updated --event Removed --event Renamed '
            . '--include=\'\\.phel$\' --include=\'\\.cljc$\' --exclude=\'.*\' '
            . implode(' ', $args);
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
        $this->stderr = null;

        if (is_resource($this->process)) {
            @proc_terminate($this->process);
            @proc_close($this->process);
        }

        $this->process = null;
    }
}
