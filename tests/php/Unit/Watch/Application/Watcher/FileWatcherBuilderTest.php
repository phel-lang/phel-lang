<?php

declare(strict_types=1);

namespace PhelTest\Unit\Watch\Application\Watcher;

use Phel\Watch\Application\SystemClock;
use Phel\Watch\Application\Watcher\FileWatcherBuilder;
use Phel\Watch\Application\Watcher\FswatchWatcher;
use Phel\Watch\Application\Watcher\InotifyWatcher;
use Phel\Watch\Application\Watcher\PollingWatcher;
use Phel\Watch\Domain\FileSystemScannerInterface;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;

use function count;
use function extension_loaded;

final class FileWatcherBuilderTest extends TestCase
{
    use RemoveDirTrait;

    private string $cleanDir;

    /** @var list<string> */
    private array $binDirs = [];

    private string|false $originalPath;

    protected function setUp(): void
    {
        $this->cleanDir = sys_get_temp_dir() . '/phel-watcher-builder-test-' . uniqid();
        mkdir($this->cleanDir, 0777, true);
        $this->originalPath = getenv('PATH');
    }

    protected function tearDown(): void
    {
        putenv($this->originalPath === false ? 'PATH' : 'PATH=' . $this->originalPath);
        $this->removeDir($this->cleanDir);
    }

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
        $this->usePathWith('inotifywait');

        $watcher = $this->builder()->create(InotifyWatcher::NAME);

        self::assertInstanceOf(InotifyWatcher::class, $watcher);
    }

    public function test_create_fswatch_when_requested_and_available(): void
    {
        $this->usePathWith('fswatch');

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
        if (extension_loaded('inotify')) {
            self::markTestSkipped('ext-inotify keeps the inotify backend available regardless of PATH');
        }

        // No backend binary is reachable, so every request has to degrade to
        // polling rather than throw, on any OS family.
        $this->usePathWith();
        $builder = $this->builder();

        self::assertInstanceOf(PollingWatcher::class, $builder->create(FswatchWatcher::NAME));
        self::assertInstanceOf(PollingWatcher::class, $builder->create(InotifyWatcher::NAME));
        self::assertInstanceOf(PollingWatcher::class, $builder->create());
    }

    /**
     * Replaces PATH with a throwaway directory holding only the named stub
     * binaries, so backend probing (`command -v <binary>`) sees exactly the
     * environment the test describes instead of whatever the host has installed.
     */
    private function usePathWith(string ...$binaries): void
    {
        $binDir = $this->cleanDir . '/bin-' . count($this->binDirs);
        mkdir($binDir, 0777, true);
        $this->binDirs[] = $binDir;

        foreach ($binaries as $binary) {
            $path = $binDir . '/' . $binary;
            file_put_contents($path, "#!/bin/sh\nexit 0\n");
            chmod($path, 0755);
        }

        putenv('PATH=' . $binDir);
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
