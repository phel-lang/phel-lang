<?php

declare(strict_types=1);

namespace PhelTest\Integration\Watch;

use Phel\Watch\Application\SystemClock;
use Phel\Watch\Application\Watcher\AbstractShellWatcher;
use Phel\Watch\Application\Watcher\InotifyWatcher;
use Phel\Watch\Domain\FileSystemScannerInterface;
use Phel\Watch\Transfer\WatchEvent;
use PHPUnit\Framework\TestCase;

use function chmod;
use function count;
use function escapeshellarg;
use function file_put_contents;
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
