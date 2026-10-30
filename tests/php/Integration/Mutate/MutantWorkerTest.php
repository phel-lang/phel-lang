<?php

declare(strict_types=1);

namespace PhelTest\Integration\Mutate;

use Phel\Mutate\Application\MutantWorker;
use PHPUnit\Framework\TestCase;

use function dirname;
use function strlen;

final class MutantWorkerTest extends TestCase
{
    public function test_request_receives_a_verdict_after_stderr_exceeds_pipe_capacity(): void
    {
        $worker = $this->spawn(<<<'PHP'
        \Phel\Shared\Process\WorkerFrame::readBlocking(STDIN);
        fwrite(STDERR, str_repeat('x', 131072) . 'final diagnostic');
        fwrite(STDOUT, \Phel\Shared\Process\WorkerFrame::encode(['verdict' => 'survived']));
        fgets(STDIN);
        PHP);

        try {
            self::assertSame(['verdict' => 'survived'], $worker->request(['type' => 'mutant'], 2.0));
        } finally {
            $worker->terminate();
        }
    }

    public function test_async_collection_receives_a_verdict_after_stderr_flooding(): void
    {
        $worker = $this->spawn(<<<'PHP'
        \Phel\Shared\Process\WorkerFrame::readBlocking(STDIN);
        fwrite(STDERR, str_repeat('x', 131072));
        fwrite(STDOUT, \Phel\Shared\Process\WorkerFrame::encode(['verdict' => 'survived']));
        fgets(STDIN);
        PHP);

        try {
            $worker->send(['type' => 'mutant'], 2.0);
            do {
                $answer = $worker->tryReceive();
                if ($answer !== null) {
                    break;
                }

                $worker->waitForOutput();
            } while (!$worker->isOverdue());

            self::assertSame(['verdict' => 'survived'], $answer);
            self::assertFalse($worker->isBusy());
        } finally {
            $worker->terminate();
        }
    }

    public function test_a_crashed_worker_retains_only_a_bounded_consumptive_stderr_tail(): void
    {
        $worker = $this->spawn(<<<'PHP'
        \Phel\Shared\Process\WorkerFrame::readBlocking(STDIN);
        fwrite(STDERR, 'discard this prefix' . str_repeat('x', 131072) . 'fatal diagnostic');
        exit(42);
        PHP);

        try {
            self::assertNull($worker->request(['type' => 'mutant'], 2.0));
            self::assertTrue($worker->isDead());
            $stderr = $worker->readStderr();
            self::assertLessThanOrEqual(16384, strlen($stderr));
            self::assertStringEndsWith('fatal diagnostic', $stderr);
            self::assertStringNotContainsString('discard this prefix', $stderr);
            self::assertSame('', $worker->readStderr());
        } finally {
            $worker->terminate();
        }
    }

    public function test_sustained_stderr_output_does_not_starve_the_request_deadline(): void
    {
        $worker = $this->spawn(<<<'PHP'
        \Phel\Shared\Process\WorkerFrame::readBlocking(STDIN);
        while (true) {
            fwrite(STDERR, str_repeat('x', 8192));
        }
        PHP);

        try {
            $start = microtime(true);
            self::assertNull($worker->request(['type' => 'mutant'], 0.2));
            self::assertLessThan(3.0, microtime(true) - $start);
            self::assertTrue($worker->isAlive());
            self::assertLessThanOrEqual(16384, strlen($worker->readStderr()));
        } finally {
            $worker->terminate();
        }
    }

    private function spawn(string $script): MutantWorker
    {
        return MutantWorker::spawn([PHP_BINARY, '-r', 'require $argv[1];' . $script, dirname(__DIR__, 4) . '/vendor/autoload.php']);
    }
}
