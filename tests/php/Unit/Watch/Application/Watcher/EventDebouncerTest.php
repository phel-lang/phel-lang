<?php

declare(strict_types=1);

namespace PhelTest\Unit\Watch\Application\Watcher;

use Phel\Watch\Application\Watcher\EventDebouncer;
use Phel\Watch\Domain\ClockInterface;
use Phel\Watch\Transfer\WatchEvent;
use PHPUnit\Framework\TestCase;

final class EventDebouncerTest extends TestCase
{
    public function test_no_flush_when_no_events_recorded(): void
    {
        $clock = new DebouncerFakeClock();
        $debouncer = new EventDebouncer($clock, 100);

        $called = 0;
        $debouncer->flushIfReady(static function (array $events) use (&$called): void {
            ++$called;
        });

        self::assertSame(0, $called);
        self::assertFalse($debouncer->hasPending());
    }

    public function test_flush_waits_for_debounce_window(): void
    {
        $clock = new DebouncerFakeClock();
        $debouncer = new EventDebouncer($clock, 100);

        $debouncer->record(new WatchEvent('/x.phel', WatchEvent::KIND_MODIFIED));
        self::assertTrue($debouncer->hasPending());

        $flushed = [];
        $callback = static function (array $events) use (&$flushed): void {
            $flushed = $events;
        };

        // Immediately (0ms elapsed) — debouncer should not yet fire.
        $debouncer->flushIfReady($callback);
        self::assertSame([], $flushed);
        self::assertTrue($debouncer->hasPending());

        // Advance past the window and flush again.
        $clock->advance(150);
        $debouncer->flushIfReady($callback);

        self::assertCount(1, $flushed);
        self::assertSame('/x.phel', $flushed[0]->path);
        self::assertFalse($debouncer->hasPending());
    }

    public function test_force_bypasses_debounce_window(): void
    {
        $clock = new DebouncerFakeClock();
        $debouncer = new EventDebouncer($clock, 500);

        $debouncer->record(new WatchEvent('/x.phel', WatchEvent::KIND_MODIFIED));

        $flushed = null;
        $debouncer->flushIfReady(static function (array $events) use (&$flushed): void {
            $flushed = $events;
        }, force: true);

        self::assertCount(1, $flushed ?? []);
        self::assertFalse($debouncer->hasPending());
    }

    public function test_coalesces_duplicate_paths_keeping_latest_non_delete(): void
    {
        $clock = new DebouncerFakeClock();
        $debouncer = new EventDebouncer($clock, 10);

        $debouncer->record(new WatchEvent('/a.phel', WatchEvent::KIND_CREATED));
        $debouncer->record(new WatchEvent('/a.phel', WatchEvent::KIND_MODIFIED));
        $debouncer->record(new WatchEvent('/b.phel', WatchEvent::KIND_MODIFIED));

        $clock->advance(20);

        $flushed = null;
        $debouncer->flushIfReady(static function (array $events) use (&$flushed): void {
            $flushed = $events;
        });

        self::assertNotNull($flushed);
        self::assertCount(2, $flushed);

        $byPath = [];
        foreach ($flushed as $event) {
            $byPath[$event->path] = $event->kind;
        }

        self::assertSame(WatchEvent::KIND_MODIFIED, $byPath['/a.phel'] ?? null, 'latest non-delete wins for duplicates');
        self::assertSame(WatchEvent::KIND_MODIFIED, $byPath['/b.phel'] ?? null);
    }

    public function test_deletion_is_sticky_across_later_modifications(): void
    {
        $clock = new DebouncerFakeClock();
        $debouncer = new EventDebouncer($clock, 10);

        $debouncer->record(new WatchEvent('/doomed.phel', WatchEvent::KIND_DELETED));
        $debouncer->record(new WatchEvent('/doomed.phel', WatchEvent::KIND_MODIFIED));

        $clock->advance(20);

        $flushed = null;
        $debouncer->flushIfReady(static function (array $events) use (&$flushed): void {
            $flushed = $events;
        });

        self::assertCount(1, $flushed ?? []);
        self::assertSame(WatchEvent::KIND_DELETED, $flushed[0]->kind);
    }

    public function test_buffer_is_cleared_after_flush(): void
    {
        $clock = new DebouncerFakeClock();
        $debouncer = new EventDebouncer($clock, 5);

        $debouncer->record(new WatchEvent('/once.phel', WatchEvent::KIND_MODIFIED));

        $clock->advance(10);

        $callCount = 0;
        $flusher = static function (array $events) use (&$callCount): void {
            ++$callCount;
        };

        $debouncer->flushIfReady($flusher);
        // Second flush right after should be a no-op: buffer is empty.
        $clock->advance(100);
        $debouncer->flushIfReady($flusher);

        self::assertSame(1, $callCount);
    }
}

final class DebouncerFakeClock implements ClockInterface
{
    private int $now = 0;

    public function nowMs(): int
    {
        return $this->now;
    }

    public function sleepMs(int $ms): void
    {
        $this->now += max(0, $ms);
    }

    public function advance(int $ms): void
    {
        $this->now += max(0, $ms);
    }
}
