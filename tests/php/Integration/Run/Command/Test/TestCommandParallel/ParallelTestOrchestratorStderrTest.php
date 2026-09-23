<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandParallel;

use Phel\Run\Application\Test\ParallelTestOrchestrator;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class ParallelTestOrchestratorStderrTest extends TestCase
{
    /**
     * A worker's stderr is only read when it dies. One that writes more than
     * the pipe buffer holds (deprecation notices replayed from a warm cache
     * reach tens of KiB) blocks on the write and never answers, unless the
     * parent keeps draining the pipe while it waits.
     */
    public function test_a_worker_flooding_stderr_still_answers(): void
    {
        $orchestrator = new ParallelTestOrchestrator(PHP_BINARY, __DIR__ . '/Fixtures/stderr-flooding-worker.php');
        $output = new BufferedOutput();

        $outcome = $orchestrator->run(
            [
                new NamespaceInformation('/app/a.phel', 'app.a-test', []),
                new NamespaceInformation('/app/b.phel', 'app.b-test', []),
            ],
            [],
            2,
            $output,
        );

        self::assertTrue($outcome->ok, $output->fetch());
    }

    /**
     * A namespace that kills its worker on every retry must not take the
     * queue down with it: the next namespace goes to a fresh worker.
     */
    public function test_a_namespace_that_exhausts_its_retries_leaves_the_queue_running(): void
    {
        $orchestrator = new ParallelTestOrchestrator(PHP_BINARY, __DIR__ . '/Fixtures/dying-worker.php');
        $output = new BufferedOutput();

        $outcome = $orchestrator->run(
            [
                new NamespaceInformation('/app/dies.phel', 'app.dies-test', []),
                new NamespaceInformation('/app/ok.phel', 'app.ok-test', []),
            ],
            [],
            1,
            $output,
        );

        $report = $output->fetch();
        self::assertFalse($outcome->ok, $report);
        self::assertStringContainsString('exit code 3', $report);
    }

    /**
     * A PHP fatal error lands on the worker's stdout, where the parent expects
     * a frame. It must become a crash report, not an exception that aborts the
     * run before the rest of the queue is run.
     */
    public function test_a_fatal_error_on_stdout_is_reported_as_a_crash(): void
    {
        $orchestrator = new ParallelTestOrchestrator(PHP_BINARY, __DIR__ . '/Fixtures/dying-worker.php');
        $output = new BufferedOutput();

        $outcome = $orchestrator->run(
            [
                new NamespaceInformation('/app/fatal.phel', 'app.fatal-test', []),
                new NamespaceInformation('/app/ok.phel', 'app.ok-test', []),
            ],
            [],
            1,
            $output,
        );

        $report = $output->fetch();
        self::assertFalse($outcome->ok, $report);
        self::assertStringContainsString('Worker died while running app.fatal-test (exit code 255)', $report);
        self::assertStringContainsString('PHP Fatal error:  Allowed memory size exhausted', $report);
        self::assertMatchesRegularExpression('/Passed:\s+1/', $report);
    }

    /**
     * A live worker that writes outside the frame protocol can never be read
     * again; it is replaced instead of aborting the run.
     */
    public function test_a_live_worker_writing_outside_a_frame_is_replaced(): void
    {
        $orchestrator = new ParallelTestOrchestrator(PHP_BINARY, __DIR__ . '/Fixtures/dying-worker.php');
        $output = new BufferedOutput();

        $outcome = $orchestrator->run(
            [
                new NamespaceInformation('/app/stray.phel', 'app.stray-test', []),
                new NamespaceInformation('/app/ok.phel', 'app.ok-test', []),
            ],
            [],
            1,
            $output,
        );

        $report = $output->fetch();
        self::assertFalse($outcome->ok, $report);
        self::assertStringContainsString('wrote output outside the frame protocol', $report);
        self::assertStringContainsString('stdout: stray output that is not a frame', $report);
        self::assertMatchesRegularExpression('/Passed:\s+1/', $report);
    }
}
