<?php

declare(strict_types=1);

namespace Phel\Watch\Application\Watcher;

use Phel\Watch\Application\SystemClock;
use Phel\Watch\Domain\ClockInterface;
use Phel\Watch\Domain\FileSystemScannerInterface;
use Phel\Watch\Domain\FileWatcherInterface;
use Phel\Watch\Transfer\WatchEvent;

use function count;
use function escapeshellarg;
use function extension_loaded;
use function fgets;
use function implode;
use function is_resource;
use function preg_match;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function rtrim;
use function stream_set_blocking;
use function trim;

/**
 * Linux-friendly watcher. Shell-outs to the `inotifywait` binary (part of
 * `inotify-tools`) and streams events. The pure-`ext-inotify` path is not
 * implemented here; the shell-out is portable, works across distros, and
 * matches the behaviour of the macOS `fswatch` backend.
 */
final class InotifyWatcher implements FileWatcherInterface
{
    public const string NAME = 'inotify';

    /** @var resource|null */
    private mixed $process = null;

    /** @var resource|null */
    private mixed $stdout = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private bool $running = false;

    public function __construct(
        private readonly FileSystemScannerInterface $scanner,
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly string $binaryPath = 'inotifywait',
        private readonly int $debounceMs = 100,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    /**
     * True when either the `inotify` PHP extension is loaded or the
     * `inotifywait` CLI is available on PATH. The latter is the default
     * transport.
     */
    public static function isAvailable(string $binary = 'inotifywait'): bool
    {
        if (extension_loaded('inotify')) {
            return true;
        }

        $handle = @popen('command -v ' . escapeshellarg($binary) . ' 2>/dev/null', 'r');
        if (!is_resource($handle)) {
            return false;
        }

        $out = (string) fgets($handle);
        pclose($handle);

        return trim($out) !== '';
    }

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
        stream_set_blocking($this->stdout, false);

        $this->scanner->snapshot($paths);

        /** @var list<WatchEvent> $pending */
        $pending = [];
        $lastEventAt = 0;

        /** @psalm-suppress RedundantCondition */
        while ($this->running && $this->isAlive()) {
            $line = @fgets($this->stdout);
            if ($line !== false) {
                $event = $this->parseLine($line);
                if ($event instanceof WatchEvent) {
                    $pending[] = $event;
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
     * @param list<string> $paths
     */
    private function buildCommand(array $paths): string
    {
        $args = [];
        foreach ($paths as $path) {
            $args[] = escapeshellarg($path);
        }

        // `-m` monitor, `-r` recursive, events close_write/create/delete/move,
        // format "<file>\t<event>" so parsing stays simple.
        return escapeshellarg($this->binaryPath)
            . ' -m -r --format \'%w%f\t%e\' --quiet '
            . '-e close_write -e create -e delete -e move '
            . implode(' ', $args);
    }

    private function parseLine(string $line): ?WatchEvent
    {
        $line = rtrim($line);
        if ($line === '') {
            return null;
        }

        $parts = explode("\t", $line, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$path, $events] = $parts;
        if (preg_match('/\.(phel|cljc)$/', $path) !== 1) {
            return null;
        }

        if (str_contains($events, 'DELETE')) {
            $kind = WatchEvent::KIND_DELETED;
        } elseif (str_contains($events, 'CREATE')) {
            $kind = WatchEvent::KIND_CREATED;
        } else {
            $kind = WatchEvent::KIND_MODIFIED;
        }

        return new WatchEvent($path, $kind);
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
