<?php

declare(strict_types=1);

namespace PhelTest\Unit\Watch\Application\Watcher;

use Phel\Watch\Application\Watcher\PollingWatcher;
use Phel\Watch\Domain\ClockInterface;
use Phel\Watch\Domain\FileSystemScannerInterface;
use Phel\Watch\Transfer\WatchEvent;
use PHPUnit\Framework\TestCase;

use function count;

final class PollingWatcherTest extends TestCase
{
    public function test_it_detects_modified_file(): void
    {
        $scanner = new FakeScanner([
            ['/a.phel' => ['mtime' => 1, 'size' => 10]],
            ['/a.phel' => ['mtime' => 2, 'size' => 12]],
        ]);
        $clock = new FakeClock();
        $watcher = new PollingWatcher($scanner, $clock, pollIntervalMs: 10, debounceMs: 50, maxIterations: 3);

        /** @var list<list<WatchEvent>> $calls */
        $calls = [];
        $watcher->watch(['/a.phel'], static function (array $events) use (&$calls): void {
            $calls[] = $events;
        });

        self::assertNotEmpty($calls);
        $flat = array_merge(...$calls);
        self::assertSame('/a.phel', $flat[0]->path);
        self::assertSame(WatchEvent::KIND_MODIFIED, $flat[0]->kind);
    }

    public function test_it_detects_created_and_deleted_files(): void
    {
        $scanner = new FakeScanner([
            ['/a.phel' => ['mtime' => 1, 'size' => 10]],
            [
                '/a.phel' => ['mtime' => 1, 'size' => 10],
                '/b.phel' => ['mtime' => 1, 'size' => 20],
            ],
            ['/b.phel' => ['mtime' => 1, 'size' => 20]],
        ]);
        $clock = new FakeClock();
        $watcher = new PollingWatcher($scanner, $clock, pollIntervalMs: 10, debounceMs: 20, maxIterations: 4);

        $allEvents = [];
        $watcher->watch(['/watch'], static function (array $events) use (&$allEvents): void {
            foreach ($events as $event) {
                $allEvents[] = $event;
            }
        });

        $paths = array_map(static fn(WatchEvent $e): string => $e->path, $allEvents);
        $kinds = array_map(static fn(WatchEvent $e): string => $e->kind, $allEvents);

        self::assertContains('/b.phel', $paths);
        self::assertContains(WatchEvent::KIND_CREATED, $kinds);
        self::assertContains(WatchEvent::KIND_DELETED, $kinds);
    }

    public function test_it_debounces_multiple_changes_into_one_callback(): void
    {
        // Three consecutive changes should coalesce into one callback when
        // debounce has not elapsed between them.
        $scanner = new FakeScanner([
            ['/a.phel' => ['mtime' => 1, 'size' => 10]],
            ['/a.phel' => ['mtime' => 2, 'size' => 12]],
            ['/a.phel' => ['mtime' => 3, 'size' => 14]],
            ['/a.phel' => ['mtime' => 3, 'size' => 14]],
        ]);
        $clock = new FakeClock();
        $watcher = new PollingWatcher($scanner, $clock, pollIntervalMs: 10, debounceMs: 25, maxIterations: 5);

        $callbackCount = 0;
        $totalEvents = 0;
        $watcher->watch(['/a.phel'], static function (array $events) use (&$callbackCount, &$totalEvents): void {
            ++$callbackCount;
            $totalEvents += count($events);
        });

        self::assertGreaterThan(0, $callbackCount);
        self::assertSame(1, $totalEvents, 'coalesce duplicate path events into one per batch');
    }

    public function test_it_stops_when_stop_is_called(): void
    {
        $scanner = new FakeScanner([[], []]);
        $clock = new FakeClock();
        $watcher = new PollingWatcher($scanner, $clock, pollIntervalMs: 10, debounceMs: 20, maxIterations: 1);

        $watcher->stop();
        $watcher->watch(['/nothing'], static function (array $_events): void {});

        // Reaching here without looping indefinitely is the assertion.
        self::assertSame('polling', $watcher->name());
    }
}

final class FakeScanner implements FileSystemScannerInterface
{
    private int $index = 0;

    /**
     * @param list<array<string, array{mtime:int, size:int}>> $snapshots
     */
    public function __construct(private array $snapshots) {}

    public function snapshot(array $paths): array
    {
        $snap = $this->snapshots[$this->index] ?? end($this->snapshots);
        if ($this->index < count($this->snapshots) - 1) {
            ++$this->index;
        }

        return $snap;
    }
}

final class FakeClock implements ClockInterface
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
}
