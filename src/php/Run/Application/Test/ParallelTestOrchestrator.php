<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Run\Domain\Test\TestCommandOptions;
use Phel\Shared\NamespaceInformation;
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
 * with a generous timeout ({@see self::SELECT_TIMEOUT_MICROS}). No threads,
 * no pcntl, no shared memory. Parent and workers exchange length-prefixed
 * JSON frames ({@see WorkerFrame}).
 */
final readonly class ParallelTestOrchestrator
{
    /**
     * Tight enough that an idle pool wakes up snappily when a worker
     * crashes; loose enough that we don't burn CPU on syscall churn
     * while every worker is busy.
     */
    private const int SELECT_TIMEOUT_MICROS = 100_000;

    /**
     * A worker occasionally fails a namespace with a transient runtime error
     * (a rare load race) rather than a genuine test failure. Re-run such a
     * namespace on a fresh worker up to this many times before surfacing the
     * error, so a flaky race can't red a whole parallel run. (#2672)
     */
    private const int MAX_RETRIES_PER_NAMESPACE = 2;

    /**
     * @param list<string> $opcacheFlags `-d` flags so every worker shares one
     *                                   OPcache file cache; empty when OPcache
     *                                   is unavailable
     */
    public function __construct(
        private string $phpBinary,
        private string $phelBinary,
        private array $opcacheFlags = [],
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
            $retried = $this->runDispatchLoop($workers, $namespaces, $optionsPhel, $buffer);
        } finally {
            foreach ($workers as $worker) {
                $worker->terminate();
            }
        }

        $buffer->finishProgress();
        $this->persistLastFailed($options, $buffer->allFailedTests());
        $this->printSummary($output, $buffer->totals(), $total, $effectiveWorkerCount, microtime(true) - $startedAt, $retried);

        return $buffer->overallOk();
    }

    private function printSummary(
        OutputInterface $output,
        Counts $totals,
        int $namespacesRun,
        int $workerCount,
        float $wallSeconds,
        int $retried,
    ): void {
        $output->writeln('');
        $output->writeln(sprintf('Passed:  %d', $totals->pass));
        $output->writeln(sprintf('Failed:  %d', $totals->failed));
        $output->writeln(sprintf('Error:   %d', $totals->error));
        if ($totals->skipped > 0) {
            $output->writeln(sprintf('Skipped: %d', $totals->skipped));
        }

        $output->writeln(sprintf('Total:   %d', $totals->total));
        if ($retried > 0) {
            $output->writeln(sprintf('Retried: %d namespace(s) after a transient worker error', $retried));
        }

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
            $workers[] = TestWorkerHandle::spawn($this->phpBinary, $this->phelBinary, $this->opcacheFlags);
        }

        return $workers;
    }

    /**
     * @param array<int, TestWorkerHandle> $workers
     * @param list<NamespaceInformation>   $namespaces
     */
    private function runDispatchLoop(
        array &$workers,
        array $namespaces,
        string $optionsPhel,
        OrderedResultBuffer $buffer,
    ): int {
        $total = count($namespaces);
        $nextToDispatch = 0;
        $retriedIndexes = [];
        $retriesLeft = array_fill(0, $total, self::MAX_RETRIES_PER_NAMESPACE);

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
                return count($retriedIndexes);
            }

            foreach ($this->waitForReadyWorkers($busyByStream) as $worker) {
                $result = $this->consumeWorker($worker);
                if (!$result instanceof WorkerResult) {
                    continue;
                }

                // A thrown worker error (not a genuine test failure) is most
                // likely a transient race; re-run the namespace on a FRESH
                // worker, since this one's process may be left in a bad state.
                if ($result->error !== null && ($retriesLeft[$result->index] ?? 0) > 0) {
                    --$retriesLeft[$result->index];
                    $retriedIndexes[$result->index] = true;
                    $worker = $this->replaceWithFreshWorker($workers, $worker);
                    $this->dispatch($worker, $namespaces[$result->index], $result->index, $optionsPhel);
                    continue;
                }

                $buffer->record($result);

                if ($nextToDispatch < $total) {
                    $this->dispatch($worker, $namespaces[$nextToDispatch], $nextToDispatch, $optionsPhel);
                    ++$nextToDispatch;
                }
            }
        }

        return count($retriedIndexes);
    }

    /**
     * Stream-resource id → worker. Lets us route a `stream_select` wake
     * straight to the worker that produced data instead of iterating the
     * whole pool.
     *
     * @param array<int, TestWorkerHandle> $workers
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
     * Drain a worker that became readable. Returns the decoded result — which
     * the caller records or retries — or null when no full frame is ready yet.
     */
    private function consumeWorker(TestWorkerHandle $worker): ?WorkerResult
    {
        $frame = $worker->tryReadFrame();
        if ($frame !== null) {
            $worker->clearAssignment();
            return WorkerResult::fromFrame($frame);
        }

        if (!$worker->isAlive() && $worker->tryReadFrame() === null) {
            $index = $worker->assignedIndex();
            $result = $index === null
                ? null
                : WorkerResult::fromCrash(
                    $index,
                    $worker->assignedNamespace() ?? '<unknown>',
                    $worker->readStderrNonBlocking(),
                );
            $worker->clearAssignment();
            return $result;
        }

        return null;
    }

    /**
     * Terminate a (possibly wedged) worker and swap a fresh one into its pool
     * slot, so a retried namespace runs in a clean process.
     *
     * @param array<int, TestWorkerHandle> $workers
     */
    private function replaceWithFreshWorker(array &$workers, TestWorkerHandle $old): TestWorkerHandle
    {
        $old->terminate();
        $fresh = TestWorkerHandle::spawn($this->phpBinary, $this->phelBinary, $this->opcacheFlags);

        // Never drop the fresh handle: a worker missing from the pool is never
        // polled or terminated, which would hang the dispatch loop and leak it.
        $key = array_search($old, $workers, true);
        if ($key === false) {
            $workers[] = $fresh;
        } else {
            $workers[$key] = $fresh;
        }

        return $fresh;
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
