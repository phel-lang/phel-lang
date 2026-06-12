<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\TestWatchLoop;
use Phel\Run\Domain\Test\WatchFileScannerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function count;

final class TestWatchLoopTest extends TestCase
{
    public function test_runs_once_and_waits_when_nothing_changes(): void
    {
        $loop = new TestWatchLoop($this->scannerReturning([
            ['a.phel' => 1],
            ['a.phel' => 1],
            ['a.phel' => 1],
        ]));

        $runs = 0;
        $output = new BufferedOutput();

        $exitCode = $loop->run(
            ['/src'],
            static function () use (&$runs): int {
                ++$runs;

                return 0;
            },
            $output,
            static function (int $ms): void {},
            maxRuns: 1,
        );

        self::assertSame(0, $exitCode);
        self::assertSame(1, $runs);
        self::assertStringContainsString('Watching for file changes', $output->fetch());
    }

    public function test_reruns_when_a_snapshot_changes(): void
    {
        $loop = new TestWatchLoop($this->scannerReturning([
            ['a.phel' => 1],  // after initial run
            ['a.phel' => 2],  // poll detects change
            ['a.phel' => 2],  // re-scan after second run
        ]));

        $exitCodes = [1, 0];
        $runs = 0;
        $output = new BufferedOutput();

        $exitCode = $loop->run(
            ['/src'],
            static function () use (&$runs, $exitCodes): int {
                return $exitCodes[$runs++];
            },
            $output,
            static function (int $ms): void {},
            maxRuns: 2,
        );

        self::assertSame(0, $exitCode, 'returns the exit code of the most recent run');
        self::assertSame(2, $runs);
        self::assertStringContainsString('Change detected, re-running tests', $output->fetch());
    }

    public function test_unchanged_polls_do_not_rerun(): void
    {
        $loop = new TestWatchLoop($this->scannerReturning([
            ['a.phel' => 1],
            ['a.phel' => 1],
            ['a.phel' => 1],
            ['a.phel' => 2],
            ['a.phel' => 2],
        ]));

        $runs = 0;
        $sleeps = 0;

        $loop->run(
            ['/src'],
            static function () use (&$runs): int {
                ++$runs;

                return 0;
            },
            new BufferedOutput(),
            static function (int $ms) use (&$sleeps): void {
                ++$sleeps;
            },
            maxRuns: 2,
        );

        self::assertSame(2, $runs);
        self::assertSame(3, $sleeps, 'two idle polls plus the one that detected the change');
    }

    /**
     * @param list<array<string, int>> $snapshots consecutive snapshot() return values; the last one repeats
     */
    private function scannerReturning(array $snapshots): WatchFileScannerInterface
    {
        return new class($snapshots) implements WatchFileScannerInterface {
            public function __construct(private array $snapshots) {}

            public function snapshot(array $directories): array
            {
                /** @var array<string, int> $next */
                $next = count($this->snapshots) > 1
                    ? array_shift($this->snapshots)
                    : $this->snapshots[0];

                return $next;
            }
        };
    }
}
