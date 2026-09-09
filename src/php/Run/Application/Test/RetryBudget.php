<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use function array_fill;
use function count;

/**
 * Decides which namespaces of a parallel run may be re-dispatched to a
 * fresh worker, and keeps the tally the summary reports.
 *
 * A worker occasionally fails a namespace with a transient runtime error
 * (a rare load race) rather than with a verdict about the source. Re-running
 * such a namespace on a fresh worker keeps a flake from reding a whole
 * run (#2672). A compile error and a failing test are verdicts: a second
 * worker reaches the same one, so they are never retried (#3271).
 *
 * @internal
 */
final class RetryBudget
{
    /** How often one namespace may be re-run before the worker fault is surfaced. */
    private const int MAX_RETRIES_PER_NAMESPACE = 2;

    /** @var array<int, int> namespace index => retries still available */
    private array $remaining;

    /** @var array<int, true> namespace indexes dispatched more than once */
    private array $retried = [];

    /** @var array<int, true> namespace indexes a retry rescued */
    private array $recovered = [];

    public function __construct(int $namespaceCount, int $maxRetriesPerNamespace = self::MAX_RETRIES_PER_NAMESPACE)
    {
        $this->remaining = array_fill(0, $namespaceCount, $maxRetriesPerNamespace);
    }

    public function allows(WorkerResult $result): bool
    {
        return $result->isRetryable() && ($this->remaining[$result->index] ?? 0) > 0;
    }

    public function consume(int $index): void
    {
        $this->remaining[$index] = ($this->remaining[$index] ?? 0) - 1;
        $this->retried[$index] = true;
    }

    /**
     * Record the result the run ends up reporting for a namespace. Only a
     * retry that turned a worker fault into a passing namespace recovered
     * anything; one that failed anyway is reported as the failure it is.
     */
    public function recordFinal(WorkerResult $result): void
    {
        if ($result->ok && isset($this->retried[$result->index])) {
            $this->recovered[$result->index] = true;
        }
    }

    public function recoveredCount(): int
    {
        return count($this->recovered);
    }
}
