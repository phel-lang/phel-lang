<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandParallel;

use Phel\Run\Application\Test\ParallelTestOrchestrator;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function array_map;

final class ParallelTestOrchestratorWorkerFailureTest extends TestCase
{
    public function test_a_worker_flooding_stderr_still_answers(): void
    {
        [$ok, $report] = $this->runOneWorker('app.noisy-test');

        self::assertTrue($ok, $report);
    }

    /**
     * The fatal error lands on stdout, where the parent expects a frame. The
     * namespace kills its worker on every retry; the next one still runs.
     */
    public function test_a_worker_dying_on_a_fatal_error_is_reported_and_the_queue_goes_on(): void
    {
        [$ok, $report] = $this->runOneWorker('app.fatal-test', 'app.ok-test');

        self::assertFalse($ok, $report);
        self::assertStringContainsString('Worker died while running app.fatal-test', $report);
        self::assertStringContainsString('stdout: PHP Fatal error:  Allowed memory size exhausted', $report);
        self::assertMatchesRegularExpression('/Passed:\s+1/', $report);
    }

    public function test_a_live_worker_writing_outside_a_frame_is_replaced(): void
    {
        [$ok, $report] = $this->runOneWorker('app.stray-test', 'app.ok-test');

        self::assertFalse($ok, $report);
        self::assertStringContainsString('(wrote output outside the frame protocol)', $report);
        self::assertStringContainsString('stdout: stray output that is not a frame', $report);
        self::assertMatchesRegularExpression('/Passed:\s+1/', $report);
    }

    /**
     * @return array{bool, string}
     */
    private function runOneWorker(string ...$namespaces): array
    {
        $orchestrator = new ParallelTestOrchestrator(PHP_BINARY, __DIR__ . '/Fixtures/misbehaving-worker.php');
        $output = new BufferedOutput();

        $outcome = $orchestrator->run(
            array_map(static fn(string $ns): NamespaceInformation => new NamespaceInformation('/app/' . $ns . '.phel', $ns, []), $namespaces),
            [],
            1,
            $output,
        );

        return [$outcome->ok, $output->fetch()];
    }
}
