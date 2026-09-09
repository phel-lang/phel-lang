<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\Counts;
use Phel\Run\Application\Test\RetryBudget;
use Phel\Run\Application\Test\WorkerOutcome;
use Phel\Run\Application\Test\WorkerResult;
use PHPUnit\Framework\TestCase;

final class RetryBudgetTest extends TestCase
{
    public function test_a_namespace_that_does_not_compile_is_never_retried(): void
    {
        $budget = new RetryBudget(3, 2);

        self::assertFalse($budget->allows($this->workerResult(1, WorkerOutcome::CompileError, ok: false)));
    }

    public function test_a_failing_test_run_is_never_retried(): void
    {
        $budget = new RetryBudget(3, 2);

        self::assertFalse($budget->allows($this->workerResult(1, WorkerOutcome::Verdict, ok: false)));
    }

    public function test_a_worker_that_died_without_a_verdict_is_retried(): void
    {
        $budget = new RetryBudget(3, 2);

        self::assertTrue($budget->allows(WorkerResult::fromCrash(1, 'app.a-test', 'segfault')));
    }

    public function test_a_worker_fault_is_retried_until_the_budget_runs_out(): void
    {
        $budget = new RetryBudget(3, 2);
        $faulted = $this->workerResult(1, WorkerOutcome::WorkerError, ok: false);

        self::assertTrue($budget->allows($faulted));
        $budget->consume(1);
        self::assertTrue($budget->allows($faulted));
        $budget->consume(1);

        self::assertFalse($budget->allows($faulted), 'the third attempt surfaces the fault');
    }

    public function test_a_budget_is_spent_per_namespace(): void
    {
        $budget = new RetryBudget(3, 1);
        $budget->consume(1);

        self::assertFalse($budget->allows($this->workerResult(1, WorkerOutcome::WorkerError, ok: false)));
        self::assertTrue($budget->allows($this->workerResult(2, WorkerOutcome::WorkerError, ok: false)));
    }

    public function test_counts_only_the_namespaces_a_retry_rescued(): void
    {
        $budget = new RetryBudget(3, 2);
        $budget->consume(1);
        $budget->recordFinal($this->workerResult(1, WorkerOutcome::Verdict, ok: true));

        self::assertSame(1, $budget->recoveredCount());
    }

    public function test_a_namespace_that_failed_anyway_recovered_nothing(): void
    {
        $budget = new RetryBudget(3, 2);
        $budget->consume(1);
        $budget->recordFinal($this->workerResult(1, WorkerOutcome::WorkerError, ok: false));

        self::assertSame(0, $budget->recoveredCount());
    }

    public function test_a_namespace_that_passed_first_time_recovered_nothing(): void
    {
        $budget = new RetryBudget(3, 2);
        $budget->recordFinal($this->workerResult(1, WorkerOutcome::Verdict, ok: true));

        self::assertSame(0, $budget->recoveredCount());
    }

    private function workerResult(int $index, WorkerOutcome $outcome, bool $ok): WorkerResult
    {
        return new WorkerResult($index, 'app.a-test', $ok, '', [], new Counts(), $outcome);
    }
}
