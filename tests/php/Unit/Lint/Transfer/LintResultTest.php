<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Transfer;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lint\Transfer\LintResult;
use PHPUnit\Framework\TestCase;

final class LintResultTest extends TestCase
{
    public function test_it_counts_diagnostics_by_severity(): void
    {
        $result = new LintResult([
            $this->diagnostic(Diagnostic::SEVERITY_ERROR),
            $this->diagnostic(Diagnostic::SEVERITY_ERROR),
            $this->diagnostic(Diagnostic::SEVERITY_WARNING),
            $this->diagnostic(Diagnostic::SEVERITY_INFO),
            $this->diagnostic(Diagnostic::SEVERITY_HINT),
        ]);

        self::assertSame(2, $result->errorCount());
        self::assertSame(1, $result->warningCount());
        self::assertSame(1, $result->infoCount());
        self::assertSame(1, $result->hintCount());
        self::assertSame(5, $result->totalCount());
        self::assertTrue($result->hasErrors());
    }

    public function test_it_reports_no_errors_when_all_warnings(): void
    {
        $result = new LintResult([$this->diagnostic(Diagnostic::SEVERITY_WARNING)]);

        self::assertFalse($result->hasErrors());
    }

    public function test_it_serialises_each_diagnostic_via_to_array(): void
    {
        $result = new LintResult([$this->diagnostic(Diagnostic::SEVERITY_WARNING)]);

        $payload = $result->toArray();
        self::assertCount(1, $payload);
        self::assertArrayHasKey('code', $payload[0]);
        self::assertSame('phel/test', $payload[0]['code']);
    }

    private function diagnostic(string $severity): Diagnostic
    {
        return new Diagnostic(
            code: 'phel/test',
            severity: $severity,
            message: 'msg',
            uri: 'f.phel',
            startLine: 1,
            startCol: 1,
            endLine: 1,
            endCol: 1,
        );
    }
}
