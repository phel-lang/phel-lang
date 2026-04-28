<?php

declare(strict_types=1);

namespace Phel\Watch\Application\Watcher;

use Phel\Watch\Domain\ClockInterface;
use Phel\Watch\Transfer\WatchEvent;

use function array_values;

/**
 * Buffers `WatchEvent`s and flushes them through a callback when the most
 * recent event is older than `$debounceMs`. Events for the same path are
 * coalesced; stronger kinds win: `deleted` > `created`/`modified`.
 *
 * The debouncer is intentionally stateful (pending buffer + last event
 * timestamp) but owns no other side-effects — time comes from
 * `ClockInterface`, flushes run the caller's callback.
 *
 * Extracted from `PollingWatcher`, `FswatchWatcher`, and `InotifyWatcher`,
 * which each re-implemented the same logic with small variations.
 */
final class EventDebouncer
{
    /** @var list<WatchEvent> */
    private array $pending = [];

    private int $lastEventAt = 0;

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly int $debounceMs,
    ) {}

    public function record(WatchEvent $event): void
    {
        $this->pending[] = $event;
        $this->lastEventAt = $this->clock->nowMs();
    }

    public function hasPending(): bool
    {
        return $this->pending !== [];
    }

    /**
     * Flush the buffer through the callback if the debounce window has
     * elapsed (or when `$force` is true). No-ops when the buffer is empty.
     *
     * @param callable(list<WatchEvent>):void $onFlush
     */
    public function flushIfReady(callable $onFlush, bool $force = false): void
    {
        if ($this->pending === []) {
            return;
        }

        if (!$force) {
            $elapsed = $this->clock->nowMs() - $this->lastEventAt;
            if ($elapsed < $this->debounceMs) {
                return;
            }
        }

        $unique = $this->coalesce($this->pending);
        $this->pending = [];
        $onFlush($unique);
    }

    /**
     * Coalesce multiple events for the same path. A deletion takes
     * precedence over an earlier creation or modification for the same
     * path; otherwise the latest event wins.
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
}
