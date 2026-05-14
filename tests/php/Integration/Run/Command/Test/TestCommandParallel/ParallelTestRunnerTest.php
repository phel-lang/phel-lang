<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandParallel;

use PHPUnit\Framework\TestCase;

use function dirname;
use function fclose;
use function is_resource;
use function proc_close;
use function proc_open;
use function stream_get_contents;

final class ParallelTestRunnerTest extends TestCase
{
    /**
     * End-to-end: spawn `./bin/phel test --parallel=2 tests/phel/walk.phel`
     * and assert (a) the parallel-mode summary marker is in the captured
     * output and (b) the run exits 0. Exercises the full stack: spawn
     * workers, dispatch frames, ordered flush, aggregated last-failed.
     */
    public function test_runs_namespaces_in_parallel_workers(): void
    {
        $project = $this->projectRoot();

        [$status, $stdout] = $this->runPhel(
            ['test', '--parallel=2', 'tests/phel/walk.phel'],
            $project,
        );

        self::assertSame(0, $status, 'expected success exit, stdout was: ' . $stdout);
        self::assertStringContainsString('worker(s).', $stdout);
        self::assertMatchesRegularExpression('/Passed:\s*20/', $stdout);
        self::assertMatchesRegularExpression('/Failed:\s*0/', $stdout);
    }

    /**
     * `--parallel=auto` resolves through CpuCountDetector and otherwise
     * follows the same pipeline; smoke-test that the resolved flow is
     * the same as an explicit worker count.
     */
    public function test_auto_worker_count_runs_to_completion(): void
    {
        $project = $this->projectRoot();

        [$status, $stdout] = $this->runPhel(
            ['test', '--parallel=auto', 'tests/phel/walk.phel'],
            $project,
        );

        self::assertSame(0, $status, 'expected success exit, stdout was: ' . $stdout);
        self::assertStringContainsString('worker(s).', $stdout);
    }

    /**
     * `--parallel=1` collapses back to the serial path; assert the
     * parallel-mode summary marker is absent so we don't accidentally
     * fan out when the user explicitly asks for serial.
     */
    public function test_parallel_one_falls_back_to_serial(): void
    {
        $project = $this->projectRoot();

        [$status, $stdout] = $this->runPhel(
            ['test', '--parallel=1', 'tests/phel/walk.phel'],
            $project,
        );

        self::assertSame(0, $status, 'expected success exit, stdout was: ' . $stdout);
        self::assertStringNotContainsString('worker(s).', $stdout);
    }

    /**
     * Sanity: the pre-run header announces the worker count and the
     * footer reports wall time. Stable feedback users can grep in CI.
     */
    public function test_emits_pre_run_header_and_wall_time_footer(): void
    {
        $project = $this->projectRoot();

        [$status, $stdout] = $this->runPhel(
            ['test', '--parallel=2', 'tests/phel/walk.phel'],
            $project,
        );

        self::assertSame(0, $status, 'expected success exit, stdout was: ' . $stdout);
        self::assertMatchesRegularExpression(
            '/Running \d+ namespace\(s\) across 2 parallel worker\(s\)\.{3}/',
            $stdout,
        );
        self::assertMatchesRegularExpression(
            '/Ran \d+ namespace\(s\) across 2 worker\(s\) in \d+\.\d{2}s\./',
            $stdout,
        );
    }

    /**
     * `--parallel=2 --reporter=tap` auto-disables parallel mode (TAP
     * needs a monotonic test counter). Verify (a) success exit, (b)
     * parallel marker absent, (c) `-v` surfaces the reason.
     */
    public function test_tap_reporter_auto_disables_parallel_with_verbose_hint(): void
    {
        $project = $this->projectRoot();

        [$status, $stdout] = $this->runPhel(
            ['test', '--parallel=2', '--reporter=tap', '-v', 'tests/phel/walk.phel'],
            $project,
        );

        self::assertSame(0, $status, 'expected success exit, stdout was: ' . $stdout);
        self::assertStringNotContainsString('parallel worker(s)', $stdout);
        self::assertStringContainsString('Ignoring --parallel', $stdout);
        self::assertStringContainsString('TAP reporter', $stdout);
    }

    public function test_rejects_invalid_parallel_value(): void
    {
        $project = $this->projectRoot();

        [$status, $combined] = $this->runPhel(
            ['test', '--parallel=banana', 'tests/phel/walk.phel'],
            $project,
        );

        self::assertSame(1, $status, 'expected failure exit, combined was: ' . $combined);
        self::assertStringContainsString('--parallel must be an integer >= 1 or "auto"', $combined);
    }

    /**
     * Live progress + aggregate summary is the on-screen view users
     * actually look at. Lock in the wire shape so the UX can't silently
     * regress: progress bar percentage marker + Passed/Failed/Error/Total
     * block.
     */
    public function test_emits_progress_bar_and_aggregate_summary_block(): void
    {
        $project = $this->projectRoot();

        [$status, $stdout] = $this->runPhel(
            ['test', '--parallel=2', 'tests/phel/walk.phel'],
            $project,
        );

        self::assertSame(0, $status, 'expected success exit, stdout was: ' . $stdout);
        self::assertMatchesRegularExpression('/\[=+\]\s*100%/', $stdout, 'progress bar should reach 100%');
        self::assertMatchesRegularExpression('/Passed:\s+\d+/', $stdout);
        self::assertMatchesRegularExpression('/Failed:\s+0/', $stdout);
        self::assertMatchesRegularExpression('/Total:\s+\d+/', $stdout);
    }

    /**
     * @param list<string> $args
     *
     * @return array{int, string}
     */
    private function runPhel(array $args, string $cwd): array
    {
        $cmd = [PHP_BINARY, $cwd . '/bin/phel', ...$args];

        $pipes = [];
        $process = proc_open(
            $cmd,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $cwd,
        );

        self::assertIsResource($process, 'failed to spawn ' . implode(' ', $cmd));

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        foreach ([1, 2] as $i) {
            if (is_resource($pipes[$i])) {
                fclose($pipes[$i]);
            }
        }

        $status = proc_close($process);

        return [$status, $stdout . $stderr];
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 7);
    }
}
