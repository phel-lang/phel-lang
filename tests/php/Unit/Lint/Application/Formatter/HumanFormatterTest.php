<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Formatter;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lint\Application\Formatter\HumanFormatter;
use Phel\Lint\Transfer\LintResult;
use PHPUnit\Framework\TestCase;

final class HumanFormatterTest extends TestCase
{
    public function test_it_formats_one_line_per_diagnostic_plus_summary(): void
    {
        $formatter = new HumanFormatter();
        $result = new LintResult([
            new Diagnostic('phel/a', Diagnostic::SEVERITY_ERROR, 'bad', '/f.phel', 2, 4, 2, 6),
            new Diagnostic('phel/b', Diagnostic::SEVERITY_WARNING, 'meh', '/f.phel', 3, 1, 3, 5),
        ]);

        $output = $formatter->format($result);

        self::assertStringContainsString('/f.phel:2:4 [error] phel/a bad', $output);
        self::assertStringContainsString('/f.phel:3:1 [warning] phel/b meh', $output);
        self::assertStringContainsString('1 error(s)', $output);
        self::assertStringContainsString('1 warning(s)', $output);
    }

    public function test_it_reports_clean_run(): void
    {
        $formatter = new HumanFormatter();

        self::assertSame('No lint issues found.', $formatter->format(new LintResult([])));
    }
}
