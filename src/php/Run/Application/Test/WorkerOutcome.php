<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use function is_string;

/**
 * What happened to one namespace in a parallel test worker, as the worker
 * itself reports it. The orchestrator retries on a fresh worker only when
 * the worker, not the namespace, is the thing that failed: repeating a
 * verdict the worker already reached about the source costs a second
 * process and reaches the same answer (#3271).
 *
 * @internal
 */
enum WorkerOutcome: string
{
    /**
     * The worker ran the namespace and reported a verdict: every test
     * passed, or some genuinely failed.
     */
    case Verdict = 'verdict';

    /**
     * The namespace does not compile. The same source compiles the same
     * way on every worker.
     */
    case CompileError = 'compile-error';

    /**
     * The worker threw while loading or running the namespace. A rare load
     * race looks like this (#2672), and the process may be left wedged.
     */
    case WorkerError = 'worker-error';

    /**
     * The worker died without reporting anything about the namespace.
     */
    case WorkerDied = 'worker-died';

    public static function fromFrameValue(mixed $raw): self
    {
        if (!is_string($raw)) {
            return self::Verdict;
        }

        return self::tryFrom($raw) ?? self::Verdict;
    }

    public function isRetryable(): bool
    {
        return $this === self::WorkerError || $this === self::WorkerDied;
    }
}
