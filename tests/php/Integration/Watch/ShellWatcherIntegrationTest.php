<?php

declare(strict_types=1);

namespace PhelTest\Integration\Watch;

use Phel\Watch\Application\SystemClock;
use Phel\Watch\Application\Watcher\AbstractShellWatcher;
use Phel\Watch\Application\Watcher\FswatchWatcher;
use Phel\Watch\Application\Watcher\InotifyWatcher;
use Phel\Watch\Domain\FileSystemScannerInterface;
use Phel\Watch\Transfer\WatchEvent;
use PHPUnit\Framework\TestCase;

use function array_map;
use function chmod;
use function count;
use function escapeshellarg;
use function file_put_contents;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function trim;
use function uniqid;
use function unlink;

use const PHP_BINARY;

final class ShellWatcherIntegrationTest extends TestCase
{
    public function test_a_line_the_external_tool_prints_reaches_the_callback_once(): void
    {
        $watcher = $this->watcherRunning('fwrite(STDOUT, "/tmp/app.phel\n"); usleep(200000);');
        $batches = [];

        $watcher->watch([], static function (array $batch) use (&$batches): void {
            $batches[] = $batch;
        });

        self::assertCount(1, $batches);
        self::assertSame('/tmp/app.phel', $batches[0][0]->path);
    }

    public function test_lines_printed_just_before_the_tool_exits_are_delivered(): void
    {
        $watcher = $this->watcherRunning('fwrite(STDOUT, "/tmp/a.phel\n/tmp/b.phel\n");');
        $events = 0;

        $watcher->watch([], static function (array $batch) use (&$events): void {
            $events += count($batch);
        });

        self::assertSame(2, $events);
    }

    public function test_inotify_events_reach_the_callback_with_the_format_it_passes(): void
    {
        $fake = sys_get_temp_dir() . '/phel-fake-inotifywait-' . uniqid('', true);
        file_put_contents($fake, <<<'SH'
            #!/bin/sh
            printf '%s\n' "$(printf '%s' "$4" | sed 's|%w%f|/tmp/app.phel|; s|%e|CLOSE_WRITE,CLOSE|')"
            SH);
        chmod($fake, 0755);

        $watcher = new InotifyWatcher($this->scanner(), new SystemClock(), $fake, 0);
        $events = [];

        try {
            $watcher->watch(['/tmp'], static function (array $batch) use (&$events): void {
                array_push($events, ...$batch);
            });
        } finally {
            unlink($fake);
        }

        self::assertCount(1, $events);
        self::assertSame('/tmp/app.phel', $events[0]->path);
        self::assertSame(WatchEvent::KIND_MODIFIED, $events[0]->kind);
    }

    public function test_fswatch_records_split_on_nul_and_keep_their_event_kind(): void
    {
        $fake = sys_get_temp_dir() . '/phel-fake-fswatch-' . uniqid('', true);
        file_put_contents($fake, <<<'SH'
            #!/bin/sh
            printf '/tmp/a.phel Updated IsFile\000/tmp/my dir/b c.phel Created IsFile\000/tmp/li'
            sleep 0.2
            printf 'ne\nbreak.phel Removed IsFile\000/tmp/notes.txt Updated IsFile\000'
            SH);
        chmod($fake, 0755);

        $watcher = new FswatchWatcher($this->scanner(), new SystemClock(), $fake, 0);
        $events = [];

        try {
            $watcher->watch(['/tmp'], static function (array $batch) use (&$events): void {
                array_push($events, ...$batch);
            });
        } finally {
            unlink($fake);
        }

        self::assertSame([
            ['/tmp/a.phel', WatchEvent::KIND_MODIFIED],
            ['/tmp/my dir/b c.phel', WatchEvent::KIND_CREATED],
            ["/tmp/line\nbreak.phel", WatchEvent::KIND_DELETED],
        ], array_map(static fn(WatchEvent $event): array => [$event->path, $event->kind], $events));
    }

    public function test_fswatch_kinds_follow_whether_the_file_is_still_there(): void
    {
        $dir = sys_get_temp_dir() . '/phel-fswatch-kinds-' . uniqid('', true);
        mkdir($dir);
        file_put_contents($dir . '/kept.phel', '');
        file_put_contents($dir . '/renamed-to.phel', '');
        $fake = $dir . '/fswatch';
        file_put_contents($fake, "#!/bin/sh\nprintf '%s\\000' "
            . escapeshellarg($dir . '/kept.phel Created Updated Removed IsFile') . ' '
            . escapeshellarg($dir . '/renamed-from.phel Renamed IsFile') . ' '
            . escapeshellarg($dir . '/renamed-to.phel Renamed IsFile') . "\n"
            . 'printf %s ' . escapeshellarg($dir . '/last.phel Updated IsFile') . "\n");
        chmod($fake, 0755);

        $watcher = new FswatchWatcher($this->scanner(), new SystemClock(), $fake, 0);
        $events = [];

        try {
            $watcher->watch([$dir], static function (array $batch) use (&$events): void {
                array_push($events, ...$batch);
            });
        } finally {
            foreach (['kept.phel', 'renamed-to.phel', 'fswatch'] as $file) {
                unlink($dir . '/' . $file);
            }

            rmdir($dir);
        }

        self::assertSame([
            [$dir . '/kept.phel', WatchEvent::KIND_CREATED],
            [$dir . '/renamed-from.phel', WatchEvent::KIND_DELETED],
            [$dir . '/renamed-to.phel', WatchEvent::KIND_CREATED],
            [$dir . '/last.phel', WatchEvent::KIND_MODIFIED],
        ], array_map(static fn(WatchEvent $event): array => [$event->path, $event->kind], $events));
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

    private function watcherRunning(string $script): AbstractShellWatcher
    {
        $scanner = $this->scanner();

        return new class($scanner, new SystemClock(), PHP_BINARY, 0, $script) extends AbstractShellWatcher {
            public function __construct(
                FileSystemScannerInterface $scanner,
                SystemClock $clock,
                string $binaryPath,
                int $debounceMs,
                private readonly string $script,
            ) {
                parent::__construct($scanner, $clock, $binaryPath, $debounceMs);
            }

            public function name(): string
            {
                return 'fake';
            }

            protected function buildCommand(array $paths): string
            {
                return escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($this->script);
            }

            protected function parseLine(string $line): WatchEvent
            {
                return new WatchEvent(trim($line), WatchEvent::KIND_MODIFIED);
            }
        };
    }
}
