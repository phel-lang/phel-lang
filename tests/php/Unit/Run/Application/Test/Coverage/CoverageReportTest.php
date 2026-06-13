<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test\Coverage;

use Phel\Run\Application\Test\Coverage\CoverageFile;
use Phel\Run\Application\Test\Coverage\CoverageReport;
use PHPUnit\Framework\TestCase;

final class CoverageReportTest extends TestCase
{
    public function test_text_report_lists_files_and_total(): void
    {
        $report = new CoverageReport([
            new CoverageFile('/proj/src/calc.phel', [2 => true, 3 => false]),
            new CoverageFile('/proj/src/util.phel', [1 => true]),
        ], 'pcov');

        $text = $report->toText();

        self::assertStringContainsString('Coverage (pcov)', $text);
        self::assertStringContainsString('calc.phel', $text);
        self::assertStringContainsString('util.phel', $text);
        self::assertStringContainsString('Total', $text);
        self::assertStringContainsString('66.7%', $text); // 2 of 3 lines total
    }

    public function test_text_report_when_empty(): void
    {
        $report = new CoverageReport([], 'xdebug');

        self::assertStringContainsString('no project source files', $report->toText());
    }

    public function test_clover_report_is_valid_xml_with_metrics(): void
    {
        $report = new CoverageReport([
            new CoverageFile('/proj/src/calc.phel', [2 => true, 3 => false]),
        ], 'pcov');

        $clover = $report->toClover(1_700_000_000);

        $xml = simplexml_load_string($clover);
        self::assertNotFalse($xml, 'clover output is well-formed XML');
        self::assertStringContainsString('<file name="/proj/src/calc.phel">', $clover);
        self::assertStringContainsString('<line num="2" type="stmt" count="1"/>', $clover);
        self::assertStringContainsString('<line num="3" type="stmt" count="0"/>', $clover);
        self::assertStringContainsString('statements="2" coveredstatements="1"', $clover);
    }

    public function test_totals_aggregate_across_files(): void
    {
        $report = new CoverageReport([
            new CoverageFile('/a.phel', [1 => true, 2 => true]),
            new CoverageFile('/b.phel', [1 => false, 2 => false]),
        ], 'pcov');

        self::assertSame(4, $report->totalCoverable());
        self::assertSame(2, $report->totalCovered());
        self::assertSame(50.0, $report->totalPercentage());
    }
}
