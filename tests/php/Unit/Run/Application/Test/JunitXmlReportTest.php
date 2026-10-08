<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\JunitXmlReport;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class JunitXmlReportTest extends TestCase
{
    private const string HEADER = '<?xml version="1.0" encoding="UTF-8"?>';

    public function test_no_document_renders_an_empty_report(): void
    {
        self::assertSame(
            self::HEADER . '<testsuites tests="0" failures="0" errors="0" skipped="0" time="0.0"></testsuites>',
            new JunitXmlReport()->render(),
        );
    }

    public function test_joins_suites_in_index_order_and_sums_the_totals(): void
    {
        $report = new JunitXmlReport();
        $report->add(1, $this->document('2', '0', '1', '1', '<testsuite name="b"><testcase name="b1"></testcase></testsuite>'));
        $report->add(0, $this->document('3', '1', '0', '0', '<testsuite name="a"><testcase name="a1"><failure message="x &gt; y" type="AssertionFailed">(= 1 2)</failure></testcase></testsuite>'));
        $report->add(2, '');

        self::assertSame(
            self::HEADER . '<testsuites tests="5" failures="1" errors="1" skipped="1" time="0.0">'
            . '<testsuite name="a"><testcase name="a1"><failure message="x &gt; y" type="AssertionFailed">(= 1 2)</failure></testcase></testsuite>'
            . '<testsuite name="b"><testcase name="b1"></testcase></testsuite>'
            . '</testsuites>',
            $report->render(),
        );
    }

    private function document(string $tests, string $failures, string $errors, string $skipped, string $suites): string
    {
        return self::HEADER . sprintf(
            '<testsuites tests="%s" failures="%s" errors="%s" skipped="%s" time="0.0">%s</testsuites>',
            $tests,
            $failures,
            $errors,
            $skipped,
            $suites,
        );
    }
}
