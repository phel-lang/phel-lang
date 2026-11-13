<?php

declare(strict_types=1);

namespace PhelTest\Integration\Watch;

use Phel\Watch\Application\SystemClock;
use Phel\Watch\Application\Watcher\AbstractShellWatcher;
use Phel\Watch\Domain\FileSystemScannerInterface;
use Phel\Watch\Transfer\WatchEvent;
use PHPUnit\Framework\TestCase;

use function count;
use function escapeshellarg;
use function trim;

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

    private function watcherRunning(string $script): AbstractShellWatcher
    {
        $scanner = new class() implements FileSystemScannerInterface {
            public function snapshot(array $paths): array
            {
                return [];
            }
        };

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
