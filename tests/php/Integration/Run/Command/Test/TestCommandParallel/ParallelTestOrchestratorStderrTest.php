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
}
