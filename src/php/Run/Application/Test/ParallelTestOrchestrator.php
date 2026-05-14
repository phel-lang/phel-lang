<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Run\Domain\Test\TestCommandOptions;
use Symfony\Component\Console\Output\OutputInterface;

use function array_unique;
use function array_values;
use function count;
use function dirname;
use function file_put_contents;
use function implode;
use function is_dir;
use function is_string;
use function max;
use function min;
use function mkdir;
use function sprintf;
use function stream_select;

/**
 * Drives a pool of {@see TestWorkerHandle} subprocesses, dispatching one
 * namespace per work frame, buffering each worker's captured output, and
 * flushing it in original namespace-discovery order so the on-screen view
 * stays deterministic across runs.
 *
 * Concurrency primitive: blocking {@see stream_select} over worker stdouts
 * with a generous timeout. No threads, no pcntl, no shared memory.
 */
final readonly class ParallelTestOrchestrator
{
    /**
     * Tight enough that an idle pool wakes up snappily when a worker
     * crashes; loose enough that we don't burn CPU on syscall churn
     * while every worker is busy.
     */
    private const int SELECT_TIMEOUT_MICROS = 100_000;

    public function __construct(
        private string $phpBinary,
        private string $phelBinary,
    ) {}

    /**
     * @param list<NamespaceInformation> $namespaces
     * @param array<string, mixed>       $options
     */
    public function run(array $namespaces, array $options, int $workerCount, OutputInterface $output): bool
    {
        $total = count($namespaces);
        if ($total === 0) {
            return true;
        }

        $effectiveWorkerCount = max(1, min($workerCount, $total));
        $optionsPhel = $this->preparePhelOptionsForWorker($options);

        $output->writeln(sprintf(
            'Running %d namespace(s) across %d parallel worker(s)...',
            $total,
            $effectiveWorkerCount,
        ));

        $workers = $this->spawnWorkers($effectiveWorkerCount);
        $buffer = new OrderedResultBuffer($total, $output);

        $startedAt = microtime(true);
        try {
            $this->runDispatchLoop($workers, $namespaces, $optionsPhel, $buffer);
        } finally {
            foreach ($workers as $worker) {
                $worker->terminate();
            }
        }

        $buffer->finishProgress();
        $this->persistLastFailed($options, $buffer->allFailedTests());
        $this->printSummary($output, $buffer->totals(), $total, $effectiveWorkerCount, microtime(true) - $startedAt);

        return $buffer->overallOk();
    }

    private function printSummary(
        OutputInterface $output,
        Counts $totals,
        int $namespacesRun,
        int $workerCount,
        float $wallSeconds,
    ): void {
        $output->writeln('');
        $output->writeln(sprintf('Passed:  %d', $totals->pass));
        $output->writeln(sprintf('Failed:  %d', $totals->failed));
        $output->writeln(sprintf('Error:   %d', $totals->error));
        if ($totals->skipped > 0) {
            $output->writeln(sprintf('Skipped: %d', $totals->skipped));
        }

        $output->writeln(sprintf('Total:   %d', $totals->total));
        $output->writeln('');
        $output->writeln(sprintf(
            'Ran %d namespace(s) across %d worker(s) in %.2fs.',
            $namespacesRun,
            $workerCount,
            $wallSeconds,
        ));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function preparePhelOptionsForWorker(array $options): string
    {
        // Workers must not race on the shared last-failed.txt; the parent
        // aggregates the union of failed tests once all workers finish.
        $options[TestCommandOptions::LAST_FAILED_FILE] = null;

        return TestCommandOptions::fromArray($options)->asPhelHashMap();
    }

    /**
     * @return list<TestWorkerHandle>
     */
    private function spawnWorkers(int $count): array
    {
        $workers = [];
        for ($i = 0; $i < $count; ++$i) {
            $workers[] = TestWorkerHandle::spawn($this->phpBinary, $this->phelBinary);
        }

        return $workers;
    }

    /**
     * @param list<TestWorkerHandle>     $workers
     * @param list<NamespaceInformation> $namespaces
     */
    private function runDispatchLoop(
        array $workers,
        array $namespaces,
        string $optionsPhel,
        OrderedResultBuffer $buffer,
    ): void {
        $total = count($namespaces);
        $nextToDispatch = 0;

        foreach ($workers as $worker) {
            if ($nextToDispatch >= $total) {
                break;
            }

            $this->dispatch($worker, $namespaces[$nextToDispatch], $nextToDispatch, $optionsPhel);
            ++$nextToDispatch;
        }

        while (!$buffer->isComplete()) {
            $busyByStream = $this->mapBusyWorkersByStream($workers);
            if ($busyByStream === []) {
                return;
            }

            foreach ($this->waitForReadyWorkers($busyByStream) as $worker) {
                if (!$this->consumeWorker($worker, $buffer)) {
                    continue;
                }

                if ($nextToDispatch < $total) {
                    $this->dispatch($worker, $namespaces[$nextToDispatch], $nextToDispatch, $optionsPhel);
                    ++$nextToDispatch;
                }
            }
        }
    }

    /**
     * Stream-resource id → worker. Lets us route a `stream_select` wake
     * straight to the worker that produced data instead of iterating the
     * whole pool.
     *
     * @param list<TestWorkerHandle> $workers
     *
     * @return array<int, TestWorkerHandle>
     */
    private function mapBusyWorkersByStream(array $workers): array
    {
        $map = [];
        foreach ($workers as $worker) {
            if ($worker->isIdle()) {
                continue;
            }

            /** @psalm-suppress InvalidArgument resource → int cast for use as array key */
            $map[(int) $worker->stdoutHandle()] = $worker;
        }

        return $map;
    }

    /**
     * Block on `stream_select` until at least one of the busy workers
     * has data ready (or the timeout fires). Returns the ready workers
     * in the order their streams came back.
     *
     * @param array<int, TestWorkerHandle> $busyByStream
     *
     * @return list<TestWorkerHandle>
     */
    private function waitForReadyWorkers(array $busyByStream): array
    {
        $reads = [];
        foreach ($busyByStream as $worker) {
            $reads[] = $worker->stdoutHandle();
        }

        $writes = null;
        $exceptions = null;
        $ready = @stream_select($reads, $writes, $exceptions, 0, self::SELECT_TIMEOUT_MICROS);
        if ($ready === false || $ready === 0) {
            // No data; let callers re-evaluate liveness on the next loop.
            return array_values($busyByStream);
        }

        $out = [];
        foreach ($reads as $stream) {
            /** @psalm-suppress InvalidArgument resource → int cast for use as array key */
            $key = (int) $stream;
            if (isset($busyByStream[$key])) {
                $out[] = $busyByStream[$key];
            }
        }

        return $out;
    }

    /**
     * Drain a worker that became readable. Returns true when a result was
     * recorded (worker is now free to take the next assignment).
     */
    private function consumeWorker(TestWorkerHandle $worker, OrderedResultBuffer $buffer): bool
    {
        $frame = $worker->tryReadFrame();
        if ($frame !== null) {
            $buffer->record(WorkerResult::fromFrame($frame));
            $worker->clearAssignment();
            return true;
        }

        if (!$worker->isAlive() && $worker->tryReadFrame() === null) {
            $buffer->recordCrash(
                $worker->assignedIndex(),
                $worker->assignedNamespace() ?? '<unknown>',
                $worker->readStderrNonBlocking(),
            );
            $worker->clearAssignment();
            return true;
        }

        return false;
    }

    private function dispatch(TestWorkerHandle $worker, NamespaceInformation $info, int $index, string $optionsPhel): void
    {
        $frame = WorkerFrame::encode([
            FrameKey::INDEX => $index,
            FrameKey::NS => $info->getNamespace(),
            FrameKey::FILE => $info->getFile(),
            FrameKey::OPTIONS => $optionsPhel,
        ]);
        $worker->assign($index, $info->getNamespace(), $frame);
    }

    /**
     * @param array<string, mixed> $options
     * @param list<string>         $failed
     */
    private function persistLastFailed(array $options, array $failed): void
    {
        $path = $options[TestCommandOptions::LAST_FAILED_FILE] ?? null;
        if (!is_string($path) || $path === '') {
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $unique = array_values(array_unique($failed));
        @file_put_contents($path, implode("\n", $unique));
    }
}
