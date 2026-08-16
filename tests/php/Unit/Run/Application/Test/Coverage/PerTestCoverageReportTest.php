<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test\Coverage;

use Phel\Run\Application\Test\Coverage\PerTestCoverageReport;
use PHPUnit\Framework\TestCase;

use function json_decode;

final class PerTestCoverageReportTest extends TestCase
{
    public function test_json_carries_both_indexes_with_string_keyed_lines(): void
    {
        $report = new PerTestCoverageReport(
            [
                'app.t/a' => ['/p/src/calc.phel' => [2, 3]],
                'app.t/b' => [],
            ],
            ['/p/src/calc.phel' => [2 => ['app.t/a'], 3 => ['app.t/a']]],
            'pcov',
        );

        $decoded = json_decode($report->toJson(), true);

        self::assertSame(
            [
                'driver' => 'pcov',
                'tests' => [
                    'app.t/a' => ['/p/src/calc.phel' => [2, 3]],
                    'app.t/b' => [],
                ],
                'lines' => ['/p/src/calc.phel' => ['2' => ['app.t/a'], '3' => ['app.t/a']]],
            ],
            $decoded,
        );
    }

    public function test_an_empty_report_is_still_valid_json_with_both_indexes(): void
    {
        $decoded = json_decode(new PerTestCoverageReport([], [], 'xdebug')->toJson(), true);

        self::assertSame(['driver' => 'xdebug', 'tests' => [], 'lines' => []], $decoded);
    }
}
