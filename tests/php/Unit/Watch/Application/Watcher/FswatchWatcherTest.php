<?php

declare(strict_types=1);

namespace PhelTest\Unit\Watch\Application\Watcher;

use Phel\Watch\Application\Watcher\FswatchWatcher;
use Phel\Watch\Domain\FileSystemScannerInterface;
use PHPUnit\Framework\TestCase;

final class FswatchWatcherTest extends TestCase
{
    public function test_name_is_fswatch(): void
    {
        $watcher = new FswatchWatcher($this->scanner());

        self::assertSame('fswatch', $watcher->name());
        self::assertSame(FswatchWatcher::NAME, $watcher->name());
    }

    public function test_is_available_is_false_for_missing_binary(): void
    {
        self::assertFalse(FswatchWatcher::isAvailable('definitely-not-a-real-binary-xyz'));
    }

    public function test_is_available_is_true_for_a_binary_on_path(): void
    {
        // `sh` is guaranteed on PATH in any POSIX CI environment; this proves the
        // PATH-probing branch returns true (not just the missing-binary branch).
        self::assertTrue(FswatchWatcher::isAvailable('sh'));
    }

    private function scanner(): FileSystemScannerInterface
    {
        return new class() implements FileSystemScannerInterface {
            public function snapshot(array $paths): array
            {
                return [];
            }
        };
    }
}
