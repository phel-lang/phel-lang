<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Transfer;

use Phel\Api\Transfer\Diagnostic;
use PHPUnit\Framework\TestCase;

final class DiagnosticTest extends TestCase
{
    public function test_it_exposes_fields_via_readonly_props(): void
    {
        $diagnostic = new Diagnostic(
            code: 'PHEL001',
            severity: Diagnostic::SEVERITY_ERROR,
            message: 'something broke',
            uri: 'user.phel',
            startLine: 1,
            startCol: 2,
            endLine: 3,
            endCol: 4,
        );

        self::assertSame('PHEL001', $diagnostic->code);
        self::assertSame(Diagnostic::SEVERITY_ERROR, $diagnostic->severity);
        self::assertSame('something broke', $diagnostic->message);
        self::assertSame('user.phel', $diagnostic->uri);
        self::assertSame(1, $diagnostic->startLine);
        self::assertSame(2, $diagnostic->startCol);
        self::assertSame(3, $diagnostic->endLine);
        self::assertSame(4, $diagnostic->endCol);
    }

    public function test_it_serializes_to_array(): void
    {
        $diagnostic = new Diagnostic(
            code: 'PHEL002',
            severity: Diagnostic::SEVERITY_WARNING,
            message: 'arity',
            uri: 'f.phel',
            startLine: 10,
            startCol: 11,
            endLine: 12,
            endCol: 13,
        );

        self::assertSame([
            'code' => 'PHEL002',
            'severity' => 'warning',
            'message' => 'arity',
            'uri' => 'f.phel',
            'startLine' => 10,
            'startCol' => 11,
            'endLine' => 12,
            'endCol' => 13,
        ], $diagnostic->toArray());
    }
}
