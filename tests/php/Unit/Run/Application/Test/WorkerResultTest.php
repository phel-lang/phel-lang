<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\WorkerOutcome;
use Phel\Run\Application\Test\WorkerResult;
use PHPUnit\Framework\TestCase;

final class WorkerResultTest extends TestCase
{
    public function test_decodes_a_well_formed_frame(): void
    {
        $result = WorkerResult::fromFrame([
            'index' => 4,
            'ns' => 'phel.http.test',
            'ok' => true,
            'output' => '...stdout...',
            'failed-tests' => ['phel.http.test/parse-url'],
            'counts' => ['pass' => 5, 'failed' => 1, 'error' => 0, 'skipped' => 0, 'total' => 6],
            'outcome' => 'verdict',
        ]);

        self::assertSame(4, $result->index);
        self::assertSame('phel.http.test', $result->ns);
        self::assertTrue($result->ok);
        self::assertSame('...stdout...', $result->output);
        self::assertSame(['phel.http.test/parse-url'], $result->failedTests);
        self::assertSame(5, $result->counts->pass);
        self::assertSame(1, $result->counts->failed);
        self::assertSame(6, $result->counts->total);
        self::assertSame(WorkerOutcome::Verdict, $result->outcome);
        self::assertFalse($result->isRetryable(), 'a verdict is what the source deserves, on any worker');
    }

    public function test_supplies_safe_defaults_for_missing_fields(): void
    {
        $result = WorkerResult::fromFrame([]);

        self::assertSame(-1, $result->index);
        self::assertSame('', $result->ns);
        self::assertFalse($result->ok);
        self::assertSame('', $result->output);
        self::assertSame([], $result->failedTests);
        self::assertSame(0, $result->counts->total);
        self::assertSame(WorkerOutcome::Verdict, $result->outcome);
    }

    public function test_filters_non_string_entries_from_failed_tests(): void
    {
        $result = WorkerResult::fromFrame([
            'failed-tests' => ['ok', 42, null, '', 'also-ok'],
        ]);

        self::assertSame(['ok', 'also-ok'], $result->failedTests);
    }

    public function test_from_crash_returns_a_synthetic_failed_result(): void
    {
        $result = WorkerResult::fromCrash(7, 'phel.broken', "segfault\n");

        self::assertSame(7, $result->index);
        self::assertSame('phel.broken', $result->ns);
        self::assertFalse($result->ok);
        self::assertSame([], $result->failedTests);
        self::assertSame(1, $result->counts->error);
        self::assertSame(1, $result->counts->total);
        self::assertStringContainsString('Worker died while running phel.broken', $result->output);
        self::assertStringContainsString('segfault', $result->output);
        self::assertSame(WorkerOutcome::WorkerDied, $result->outcome);
        self::assertTrue($result->isRetryable(), 'a crashed worker never reported a verdict');
    }

    public function test_a_thrown_worker_error_can_be_retried(): void
    {
        $result = WorkerResult::fromFrame([
            'ok' => false,
            'outcome' => 'worker-error',
            'output' => 'Failed running phel.a: Call to a member function __invoke() on null',
            'failed-tests' => [],
        ]);

        self::assertFalse($result->ok);
        self::assertSame(WorkerOutcome::WorkerError, $result->outcome);
        self::assertTrue($result->isRetryable());
    }

    public function test_a_compile_error_is_not_retried(): void
    {
        $result = WorkerResult::fromFrame([
            'ok' => false,
            'outcome' => 'compile-error',
            'output' => "Failed to compile phel.a: Cannot resolve symbol 'nope'",
            'failed-tests' => [],
        ]);

        self::assertFalse($result->ok);
        self::assertSame(WorkerOutcome::CompileError, $result->outcome);
        self::assertFalse($result->isRetryable(), 'the same source fails to compile on every worker');
    }

    public function test_a_failing_test_run_is_not_retried(): void
    {
        $result = WorkerResult::fromFrame([
            'ok' => false,
            'outcome' => 'verdict',
            'failed-tests' => ['phel.a/some-test'],
        ]);

        self::assertFalse($result->ok);
        self::assertFalse($result->isRetryable());
        self::assertSame(['phel.a/some-test'], $result->failedTests);
    }
}
