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
