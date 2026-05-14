<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Run\Domain\Test\TestCommandOptions;
use Symfony\Component\Console\Output\OutputInterface;

use function array_unique;
use function count;
use function dirname;
use function file_put_contents;
use function implode;
use function is_array;
use function is_dir;
use function is_string;
use function max;
use function mkdir;
use function sprintf;

use function stream_select;
use function strlen;

use const PHP_EOL;

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
    private const int SELECT_TIMEOUT_MICROS = 1_000_000;

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

        // Workers will write their own last-failed list back to the parent;
        // disable per-worker writes so they don't race on the same file.
        $optionsForWorker = $options;
        $lastFailedFile = $optionsForWorker[TestCommandOptions::LAST_FAILED_FILE] ?? null;
        $optionsForWorker[TestCommandOptions::LAST_FAILED_FILE] = null;
        $optionsPhel = TestCommandOptions::fromArray($optionsForWorker)->asPhelHashMap();

        $effectiveWorkerCount = max(1, min($workerCount, $total));

        /** @var list<TestWorkerHandle> $workers */
        $workers = [];
        for ($i = 0; $i < $effectiveWorkerCount; ++$i) {
            $workers[] = TestWorkerHandle::spawn($this->phpBinary, $this->phelBinary);
        }

        /** @var array<int, array{ns: string, ok: bool, output: string, failed: list<string>}|null> $slots */
        $slots = array_fill(0, $total, null);
        $nextToFlush = 0;
        $nextToDispatch = 0;
        $overallOk = true;
        $allFailedTests = [];

        try {
            // Prime each worker with a first work item.
            foreach ($workers as $worker) {
                if ($nextToDispatch >= $total) {
                    break;
                }

                $this->dispatch($worker, $namespaces[$nextToDispatch], $nextToDispatch, $optionsPhel);
                ++$nextToDispatch;
            }

            $completed = 0;
            while ($completed < $total) {
                $reads = [];
                foreach ($workers as $worker) {
                    if (!$worker->isIdle()) {
                        $reads[] = $worker->stdoutHandle();
                    }
                }

                if ($reads === []) {
                    // Either we dispatched everything and are draining, or
                    // workers all died. Either way, drop out.
                    break;
                }

                $writes = null;
                $exceptions = null;
                @stream_select($reads, $writes, $exceptions, 0, self::SELECT_TIMEOUT_MICROS);

                foreach ($workers as $worker) {
                    if ($worker->isIdle()) {
                        continue;
                    }

                    $frame = $worker->tryReadFrame();
                    if ($frame === null) {
                        if (!$worker->isAlive() && $worker->tryReadFrame() === null) {
                            $this->handleDeadWorker($worker, $slots);
                            ++$completed;
                            $worker->clearAssignment();
                        }

                        continue;
                    }

                    $index = (int) ($frame['index'] ?? -1);
                    $ns = (string) ($frame['ns'] ?? '');
                    $ok = (bool) ($frame['ok'] ?? false);
                    $captured = (string) ($frame['output'] ?? '');
                    $failed = $this->extractStringList($frame['failed-tests'] ?? []);

                    $slots[$index] = [
                        'ns' => $ns,
                        'ok' => $ok,
                        'output' => $captured,
                        'failed' => $failed,
                    ];
                    $worker->clearAssignment();
                    ++$completed;

                    if (!$ok) {
                        $overallOk = false;
                    }

                    foreach ($failed as $name) {
                        $allFailedTests[] = $name;
                    }

                    if ($nextToDispatch < $total) {
                        $this->dispatch($worker, $namespaces[$nextToDispatch], $nextToDispatch, $optionsPhel);
                        ++$nextToDispatch;
                    }

                    while ($nextToFlush < $total && $slots[$nextToFlush] !== null) {
                        $this->flushSlot($output, $slots[$nextToFlush], $nextToFlush, $total);
                        $slots[$nextToFlush] = null;
                        ++$nextToFlush;
                    }
                }
            }
        } finally {
            foreach ($workers as $worker) {
                $worker->terminate();
            }
        }

        if (is_string($lastFailedFile) && $lastFailedFile !== '') {
            $this->writeLastFailed($lastFailedFile, $allFailedTests);
        }

        $output->writeln('');
        $output->writeln(sprintf('Ran %d namespace(s) across %d worker(s).', $total, $effectiveWorkerCount));

        return $overallOk;
    }

    /**
     * @param array{ns: string, ok: bool, output: string, failed: list<string>} $slot
     */
    private function flushSlot(OutputInterface $output, array $slot, int $index, int $total): void
    {
        $header = sprintf('--- [%d/%d] %s ---', $index + 1, $total, $slot['ns']);
        $output->writeln($header);

        $captured = $slot['output'];
        if ($captured !== '' && str_ends_with($captured, PHP_EOL)) {
            $captured = substr($captured, 0, -strlen(PHP_EOL));
        }

        if ($captured !== '') {
            $output->writeln($captured);
        }
    }

    private function dispatch(TestWorkerHandle $worker, NamespaceInformation $info, int $index, string $optionsPhel): void
    {
        $frame = WorkerFrame::encode([
            'index' => $index,
            'ns' => $info->getNamespace(),
            'file' => $info->getFile(),
            'options' => $optionsPhel,
        ]);
        $worker->assign($index, $info->getNamespace(), $frame);
    }

    /**
     * @param array<int, array{ns: string, ok: bool, output: string, failed: list<string>}|null> $slots
     */
    private function handleDeadWorker(TestWorkerHandle $worker, array &$slots): void
    {
        $index = $worker->assignedIndex();
        $ns = $worker->assignedNamespace() ?? '<unknown>';
        if ($index === null) {
            return;
        }

        $stderr = $worker->readStderrNonBlocking();
        $slots[$index] = [
            'ns' => $ns,
            'ok' => false,
            'output' => sprintf("Worker died while running %s.\n%s", $ns, $stderr),
            'failed' => [],
        ];
    }

    /**
     * @param mixed $raw
     *
     * @return list<string>
     */
    private function extractStringList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $entry) {
            if (is_string($entry) && $entry !== '') {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $failed
     */
    private function writeLastFailed(string $path, array $failed): void
    {
        $dir = dirname($path);
        if ($dir !== '' && !is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $unique = array_values(array_unique($failed));
        @file_put_contents($path, implode("\n", $unique));
    }
}
