<?php

declare(strict_types=1);

namespace PhelTest\Unit\Watch\Application\Watcher;

use Phel\Watch\Application\SystemClock;
use Phel\Watch\Application\Watcher\FileWatcherBuilder;
use Phel\Watch\Application\Watcher\FswatchWatcher;
use Phel\Watch\Application\Watcher\InotifyWatcher;
use Phel\Watch\Application\Watcher\PollingWatcher;
use Phel\Watch\Domain\FileSystemScannerInterface;
use PHPUnit\Framework\TestCase;

final class FileWatcherBuilderTest extends TestCase
{
    public function test_polling_is_always_available(): void
    {
        $builder = $this->builder();

        $watcher = $builder->polling();

        self::assertInstanceOf(PollingWatcher::class, $watcher);
        self::assertSame(PollingWatcher::NAME, $watcher->name());
    }

    public function test_create_polling_regardless_of_platform_when_requested(): void
    {
        $builder = $this->builder();

        $watcher = $builder->create(PollingWatcher::NAME);

        self::assertInstanceOf(PollingWatcher::class, $watcher);
    }

    public function test_create_polling_when_preferred_is_unknown(): void
    {
        $builder = $this->builder();

        $watcher = $builder->create('nope-not-a-backend');

        self::assertInstanceOf(PollingWatcher::class, $watcher);
    }

    public function test_case_insensitive_preferred_name(): void
    {
        $builder = $this->builder();

        $watcher = $builder->create('POLLING');

        self::assertInstanceOf(PollingWatcher::class, $watcher);
    }

    public function test_create_inotify_when_requested_and_available(): void
    {
        if (!InotifyWatcher::isAvailable()) {
            self::markTestSkipped('inotifywait not available on host');
        }

        $watcher = $this->builder()->create(InotifyWatcher::NAME);

        self::assertInstanceOf(InotifyWatcher::class, $watcher);
    }

    public function test_create_fswatch_when_requested_and_available(): void
    {
        if (!FswatchWatcher::isAvailable()) {
            self::markTestSkipped('fswatch not available on host');
        }

        $watcher = $this->builder()->create(FswatchWatcher::NAME);

        self::assertInstanceOf(FswatchWatcher::class, $watcher);
    }

    public function test_auto_detect_returns_known_backend(): void
    {
        $watcher = $this->builder()->create();

        self::assertContains(
            $watcher->name(),
            [PollingWatcher::NAME, FswatchWatcher::NAME, InotifyWatcher::NAME],
        );
    }

    public function test_fallback_to_polling_when_preferred_backend_is_unavailable(): void
    {
        // We request fswatch/inotify explicitly: when the binary is missing,
        // the builder should silently fall back to polling rather than throw.
        $builder = $this->builder();

        if (!FswatchWatcher::isAvailable()) {
            $watcher = $builder->create(FswatchWatcher::NAME);
            self::assertInstanceOf(PollingWatcher::class, $watcher);
        }

        if (!InotifyWatcher::isAvailable()) {
            $watcher = $builder->create(InotifyWatcher::NAME);
            self::assertInstanceOf(PollingWatcher::class, $watcher);
        }

        // Either branch runs depending on the host; both assertions simply
        // confirm the contract that an unavailable backend never throws.
        self::assertTrue(true);
    }

    private function builder(): FileWatcherBuilder
    {
        $scanner = new class() implements FileSystemScannerInterface {
            public function snapshot(array $paths): array
            {
                return [];
            }
        };

        return new FileWatcherBuilder($scanner, new SystemClock());
    }
}
