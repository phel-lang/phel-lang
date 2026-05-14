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
    }
}
