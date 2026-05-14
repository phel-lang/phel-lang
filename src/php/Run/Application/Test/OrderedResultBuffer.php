<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;
use function str_ends_with;
use function strlen;
use function substr;

use const PHP_EOL;

/**
 * Holds per-namespace results while the dispatch loop fans work out.
 *
 * Workers complete in arbitrary order; this buffer keeps each slot
 * keyed by the original input index and flushes them to {@see OutputInterface}
 * in input order so the on-screen view stays deterministic across runs.
 * Also tracks overall success and the union of failed test names for
 * `--last-failed` aggregation.
 */
final class OrderedResultBuffer
{
    /** @var array<int, ?WorkerResult> */
    private array $slots;

    private int $nextToFlush = 0;

    private int $completed = 0;

    private bool $overallOk = true;

    /** @var list<string> */
    private array $allFailedTests = [];

    public function __construct(
        private readonly int $total,
        private readonly OutputInterface $output,
    ) {
        $this->slots = array_fill(0, $total, null);
    }

    public function record(WorkerResult $result): void
    {
        $this->slots[$result->index] = $result;
        ++$this->completed;

        if (!$result->ok) {
            $this->overallOk = false;
        }

        foreach ($result->failedTests as $name) {
            $this->allFailedTests[] = $name;
        }

        $this->flushReady();
    }

    public function recordCrash(?int $index, string $ns, string $stderr): void
    {
        if ($index === null) {
            return;
        }

        $this->record(WorkerResult::fromCrash($index, $ns, $stderr));
    }

    public function isComplete(): bool
    {
        return $this->completed >= $this->total;
    }

    public function overallOk(): bool
    {
        return $this->overallOk;
    }

    /**
     * @return list<string>
     */
    public function allFailedTests(): array
    {
        return $this->allFailedTests;
    }

    private function flushReady(): void
    {
        while ($this->nextToFlush < $this->total && $this->slots[$this->nextToFlush] !== null) {
            $this->flushSlot($this->slots[$this->nextToFlush], $this->nextToFlush);
            $this->slots[$this->nextToFlush] = null;
            ++$this->nextToFlush;
        }
    }

    private function flushSlot(WorkerResult $result, int $index): void
    {
        $this->output->writeln(sprintf('--- [%d/%d] %s ---', $index + 1, $this->total, $result->ns));

        $captured = $result->output;
        if ($captured !== '' && str_ends_with($captured, PHP_EOL)) {
            $captured = substr($captured, 0, -strlen(PHP_EOL));
        }

        if ($captured !== '') {
            $this->output->writeln($captured);
        }
    }
}
