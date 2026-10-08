<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Api;

use Phel\Lang\SourceLocation;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Exceptions\ErrorCodeCatalog;
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
            'errorCode' => null,
            'suggestions' => [],
            'fix' => null,
        ], $diagnostic->toArray());
    }

    public function test_a_located_exception_carries_its_code_suggestions_and_catalog_fix(): void
    {
        $e = new class('Cannot resolve symbol', new SourceLocation('a.phel', 2, 3)) extends AbstractLocatedException {
            public function __construct(string $message, SourceLocation $start)
            {
                parent::__construct($message, $start);
                $this->setErrorCode(ErrorCode::UNDEFINED_SYMBOL);
                $this->setSuggestions(['println']);
            }
        };

        $diagnostic = Diagnostic::fromLocatedException($e, ErrorCode::INVALID_SPECIAL_FORM, 'a.phel');

        self::assertSame('PHEL001', $diagnostic->code);
        self::assertSame('PHEL001', $diagnostic->errorCode);
        self::assertSame(['println'], $diagnostic->suggestions);
        self::assertSame(ErrorCodeCatalog::explain(ErrorCode::UNDEFINED_SYMBOL)->fix, $diagnostic->fix);
        self::assertSame([2, 4, 2, 4], [$diagnostic->startLine, $diagnostic->startCol, $diagnostic->endLine, $diagnostic->endCol]);
    }

    public function test_a_source_span_reads_as_one_based_columns(): void
    {
        $diagnostic = Diagnostic::fromSourceSpan(
            'phel/a',
            Diagnostic::SEVERITY_WARNING,
            'm',
            'a.phel',
            new SourceLocation('a.phel', 2, 13),
            new SourceLocation('a.phel', 2, 15),
        );

        self::assertSame([2, 14, 2, 16], [$diagnostic->startLine, $diagnostic->startCol, $diagnostic->endLine, $diagnostic->endCol]);
    }

    public function test_a_span_without_locations_starts_at_the_first_column(): void
    {
        $diagnostic = Diagnostic::fromSourceSpan('phel/a', Diagnostic::SEVERITY_WARNING, 'm', 'a.phel', null, null);

        self::assertSame([1, 1, 1, 1], [$diagnostic->startLine, $diagnostic->startCol, $diagnostic->endLine, $diagnostic->endCol]);
    }

    public function test_a_new_code_keeps_the_error_code_behind_it(): void
    {
        $diagnostic = new Diagnostic('PHEL001', Diagnostic::SEVERITY_ERROR, 'm', 'a.phel', 1, 1, 1, 1, 'PHEL001', ['x'], 'fix it')
            ->withCode('phel/unresolved-symbol')
            ->withSeverity(Diagnostic::SEVERITY_WARNING);

        self::assertSame('phel/unresolved-symbol', $diagnostic->code);
        self::assertSame(Diagnostic::SEVERITY_WARNING, $diagnostic->severity);
        self::assertSame('PHEL001', $diagnostic->errorCode);
        self::assertSame(['x'], $diagnostic->suggestions);
        self::assertSame('fix it', $diagnostic->fix);
    }
}
