<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Rule\UnresolvedReferRule;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Api\ProjectIndex;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\LintRuleCodes;
use PHPUnit\Framework\TestCase;

final class UnresolvedReferRuleTest extends TestCase
{
    public function test_it_promotes_the_analyzer_unresolved_refer_error(): void
    {
        $analysis = new FileAnalysis(
            uri: 'u4.phel',
            namespace: 'app.u4',
            source: '',
            forms: [],
            projectIndex: new ProjectIndex([], []),
            semanticDiagnostics: [
                $this->diagnostic(ErrorCode::UNRESOLVED_REFER->value, "'nope' is referred from app.util, which does not define it."),
                $this->diagnostic(ErrorCode::UNDEFINED_SYMBOL->value, "Cannot resolve symbol 'x'"),
            ],
        );

        $diagnostics = new UnresolvedReferRule()->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertSame(LintRuleCodes::UNRESOLVED_REFER, $diagnostics[0]->code);
        self::assertSame("'nope' is referred from app.util, which does not define it.", $diagnostics[0]->message);
        self::assertSame(2, $diagnostics[0]->startLine);
    }

    private function diagnostic(string $code, string $message): Diagnostic
    {
        return new Diagnostic(
            code: $code,
            severity: Diagnostic::SEVERITY_ERROR,
            message: $message,
            uri: 'u4.phel',
            startLine: 2,
            startCol: 31,
            endLine: 2,
            endCol: 35,
        );
    }
}
