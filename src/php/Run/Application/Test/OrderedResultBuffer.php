<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

use function array_fill;
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
 *
 * Output policy: a {@see ProgressBar} reports progress live; the full
 * captured per-namespace block is only printed when the namespace
 * actually had a failure / error. Passing namespaces just bump the
 * progress bar so the run isn't drowned in "Passed: 0, Total: 0"
 * blocks for transitive deps.
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

    private readonly Counts $totals;

    private readonly ProgressBar $progressBar;

    public function __construct(
        private readonly int $total,
        private readonly OutputInterface $output,
    ) {
        $this->slots = array_fill(0, $total, null);
        $this->totals = new Counts();
        $this->progressBar = $this->buildProgressBar($output, $total);
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

        $this->totals->add($result->counts);
        $this->flushReady();
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

    public function totals(): Counts
    {
        return $this->totals;
    }

    public function finishProgress(): void
    {
        $this->progressBar->setMessage('done');
        $this->progressBar->finish();

        $this->output->writeln('');
    }

    private function flushReady(): void
    {
        while ($this->nextToFlush < $this->total && $this->slots[$this->nextToFlush] !== null) {
            $this->flushSlot($this->slots[$this->nextToFlush]);
            $this->slots[$this->nextToFlush] = null;
            ++$this->nextToFlush;
        }
    }

    private function flushSlot(WorkerResult $result): void
    {
        $this->progressBar->setMessage($result->ns);
        $this->progressBar->advance();

        if (!$this->shouldShowDetail($result)) {
            return;
        }

        // Pause the progress bar so its line doesn't get clobbered by
        // multi-line failure output, then redraw it underneath.
        $this->progressBar->clear();
        $this->output->writeln('');
        $this->output->writeln(sprintf('<comment>--- %s ---</comment>', $result->ns));

        $captured = $this->trimTrailingNewline($result->output);
        if ($captured !== '') {
            $this->output->writeln($captured);
        }

        $this->progressBar->display();
    }

    private function shouldShowDetail(WorkerResult $result): bool
    {
        return !$result->ok || $result->counts->hasFailures();
    }

    private function trimTrailingNewline(string $captured): string
    {
        if ($captured !== '' && str_ends_with($captured, PHP_EOL)) {
            return substr($captured, 0, -strlen(PHP_EOL));
        }

        return $captured;
    }

    private function buildProgressBar(OutputInterface $output, int $total): ProgressBar
    {
        $bar = new ProgressBar($output, $total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% — %message%');
        $bar->setMessage('starting');
        $bar->setRedrawFrequency(1);
        $bar->minSecondsBetweenRedraws(0.05);
        $bar->start();

        return $bar;
    }
}
