<?php

declare(strict_types=1);

namespace Phel\Watch\Application\Watcher;

use Phel\Watch\Domain\ClockInterface;
use Phel\Watch\Domain\FileSystemScannerInterface;
use Phel\Watch\Domain\FileWatcherInterface;
use Phel\Watch\Transfer\WatchEvent;

/**
 * Stat-based fallback watcher. Walks the given paths every `$pollIntervalMs`
 * and diffs the mtime + size snapshot with the previous one. Events emitted
 * in the same `$debounceMs` window are coalesced into a single callback
 * invocation (which stops editor-save-induced double triggers).
 */
final class PollingWatcher implements FileWatcherInterface
{
    public const string NAME = 'polling';

    private bool $running = false;

    /** @var array<string, array{mtime:int, size:int}> */
    private array $lastSnapshot = [];

    public function __construct(
        private readonly FileSystemScannerInterface $scanner,
        private readonly ClockInterface $clock,
        private readonly int $pollIntervalMs = 500,
        private readonly int $debounceMs = 100,
        private readonly int $maxIterations = 0,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function watch(array $paths, callable $onChange): void
    {
        $this->running = true;
        $this->lastSnapshot = $this->scanner->snapshot($paths);

        /** @var list<WatchEvent> $pending */
        $pending = [];
        $lastEventAt = 0;
        $iterations = 0;

        /** @psalm-suppress RedundantCondition */
        while ($this->running) {
            $this->clock->sleepMs($this->pollIntervalMs);

            $currentSnapshot = $this->scanner->snapshot($paths);
            $events = $this->diff($this->lastSnapshot, $currentSnapshot);
            $this->lastSnapshot = $currentSnapshot;

            if ($events !== []) {
                foreach ($events as $event) {
                    $pending[] = $event;
                }

                $lastEventAt = $this->clock->nowMs();
            }

            $pending = $this->flushIfReady($pending, $lastEventAt, $onChange);

            ++$iterations;
            if ($this->maxIterations > 0 && $iterations >= $this->maxIterations) {
                $this->running = false;
                // Flush any outstanding events so callers can assert on them.
                $this->flushIfReady($pending, $lastEventAt, $onChange, true);
                break;
            }
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * @param list<WatchEvent>                $pending
     * @param callable(list<WatchEvent>):void $onChange
     *
     * @return list<WatchEvent>
     */
    private function flushIfReady(array $pending, int $lastEventAt, callable $onChange, bool $force = false): array
    {
        if ($pending === []) {
            return [];
        }

        if (!$force) {
            $elapsed = $this->clock->nowMs() - $lastEventAt;
            if ($elapsed < $this->debounceMs) {
                return $pending;
            }
        }

        $unique = $this->coalesce($pending);
        $onChange($unique);

        return [];
    }

    /**
     * Coalesce multiple events for the same path into a single entry.
     *
     * @param list<WatchEvent> $events
     *
     * @return list<WatchEvent>
     */
    private function coalesce(array $events): array
    {
        /** @var array<string, WatchEvent> $byPath */
        $byPath = [];
        foreach ($events as $event) {
            // Creations trump modifications for the same file; deletions trump
            // creations. Later wins only when the kind is stronger.
            $existing = $byPath[$event->path] ?? null;
            if ($existing === null) {
                $byPath[$event->path] = $event;
                continue;
            }

            if ($existing->kind === WatchEvent::KIND_DELETED) {
                continue;
            }

            $byPath[$event->path] = $event;
        }

        return array_values($byPath);
    }

    /**
     * @param array<string, array{mtime:int, size:int}> $previous
     * @param array<string, array{mtime:int, size:int}> $current
     *
     * @return list<WatchEvent>
     */
    private function diff(array $previous, array $current): array
    {
        $events = [];
        foreach ($current as $path => $stat) {
            if (!isset($previous[$path])) {
                $events[] = new WatchEvent($path, WatchEvent::KIND_CREATED);
                continue;
            }

            if ($previous[$path]['mtime'] !== $stat['mtime'] || $previous[$path]['size'] !== $stat['size']) {
                $events[] = new WatchEvent($path, WatchEvent::KIND_MODIFIED);
            }
        }

        foreach (array_keys($previous) as $path) {
            if (!isset($current[$path])) {
                $events[] = new WatchEvent($path, WatchEvent::KIND_DELETED);
            }
        }

        return $events;
    }
}
