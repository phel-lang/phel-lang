<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

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
        ]);

        self::assertSame(4, $result->index);
        self::assertSame('phel.http.test', $result->ns);
        self::assertTrue($result->ok);
        self::assertSame('...stdout...', $result->output);
        self::assertSame(['phel.http.test/parse-url'], $result->failedTests);
        self::assertSame(5, $result->counts->pass);
        self::assertSame(1, $result->counts->failed);
        self::assertSame(6, $result->counts->total);
        self::assertNull($result->error, 'a normal result carries no thrown-error marker');
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
        self::assertNotNull($result->error, 'a crashed worker is a retryable transient error');
    }

    public function test_extracts_thrown_error_marker_so_it_can_be_retried(): void
    {
        $result = WorkerResult::fromFrame([
            'ok' => false,
            'error' => 'Call to a member function __invoke() on null',
            'failed-tests' => [],
        ]);

        self::assertFalse($result->ok);
        self::assertSame('Call to a member function __invoke() on null', $result->error);
    }

    public function test_error_is_null_when_a_failing_test_run_reports_no_thrown_error(): void
    {
        // A genuine test failure (ok=false, populated failed-tests, error=null)
        // must NOT look like a retryable transient error.
        $result = WorkerResult::fromFrame([
            'ok' => false,
            'error' => null,
            'failed-tests' => ['phel.a/some-test'],
        ]);

        self::assertFalse($result->ok);
        self::assertNull($result->error);
        self::assertSame(['phel.a/some-test'], $result->failedTests);
    }
}
