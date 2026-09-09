<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\WorkerOutcome;
use PHPUnit\Framework\TestCase;

final class WorkerOutcomeTest extends TestCase
{
    public function test_only_a_worker_fault_is_worth_running_again(): void
    {
        self::assertFalse(WorkerOutcome::Verdict->isRetryable());
        self::assertFalse(WorkerOutcome::CompileError->isRetryable());
        self::assertTrue(WorkerOutcome::WorkerError->isRetryable());
        self::assertTrue(WorkerOutcome::WorkerDied->isRetryable());
    }

    public function test_reads_the_outcome_a_worker_reported(): void
    {
        self::assertSame(WorkerOutcome::CompileError, WorkerOutcome::fromFrameValue('compile-error'));
        self::assertSame(WorkerOutcome::WorkerError, WorkerOutcome::fromFrameValue('worker-error'));
    }

    public function test_an_unreadable_outcome_is_a_verdict_so_nothing_is_retried_by_accident(): void
    {
        self::assertSame(WorkerOutcome::Verdict, WorkerOutcome::fromFrameValue(null));
        self::assertSame(WorkerOutcome::Verdict, WorkerOutcome::fromFrameValue(42));
        self::assertSame(WorkerOutcome::Verdict, WorkerOutcome::fromFrameValue('worker-exploded'));
    }
}
