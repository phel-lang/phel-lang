<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Run\Domain\Test\WatchFileScannerInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `phel test --watch` driver: runs the test suite once, then re-runs it every
 * time a watched file changes. The suite itself executes as a subprocess (the
 * `$runTests` callable), so each run starts from a clean runtime; this loop
 * only owns change detection and pacing.
 */
final readonly class TestWatchLoop
{
    public const int POLL_INTERVAL_MS = 500;

    public function __construct(
        private WatchFileScannerInterface $scanner,
    ) {}

    /**
     * Blocks until terminated (Ctrl+C) unless `$maxRuns` is given (used by
     * tests). Returns the exit code of the most recent test run.
     *
     * @param list<string>       $directories
     * @param callable():int     $runTests
     * @param callable(int):void $sleep       milliseconds; injectable for tests
     */
    public function run(
        array $directories,
        callable $runTests,
        OutputInterface $output,
        ?callable $sleep = null,
        ?int $maxRuns = null,
    ): int {
        $sleep ??= static function (int $ms): void {
            usleep($ms * 1000);
        };

        $exitCode = $runTests();
        $runs = 1;
        $snapshot = $this->scanner->snapshot($directories);
        $this->announceWatching($output);

        while ($maxRuns === null || $runs < $maxRuns) {
            $sleep(self::POLL_INTERVAL_MS);

            $next = $this->scanner->snapshot($directories);
            if ($next === $snapshot) {
                continue;
            }

            $output->writeln('');
            $output->writeln('<comment>Change detected, re-running tests...</comment>');
            $exitCode = $runTests();
            ++$runs;
            // Re-scan after the run so changes made while testing trigger
            // one more run instead of being silently absorbed.
            $snapshot = $this->scanner->snapshot($directories);
            $this->announceWatching($output);
        }

        return $exitCode;
    }

    private function announceWatching(OutputInterface $output): void
    {
        $output->writeln('<info>Watching for file changes... (press Ctrl+C to stop)</info>');
    }
}
