<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Rule\ArityMismatchRule;
use Phel\Lint\Application\Rule\CompileErrorRule;
use Phel\Lint\Application\Rule\RedundantDoRule;
use Phel\Lint\Application\Rule\UnresolvedReferRule;
use Phel\Lint\Application\Rule\UnresolvedSymbolRule;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Api\ProjectIndex;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\LintRuleCodes;
use PHPUnit\Framework\TestCase;

use function array_map;

final class CompileErrorRuleTest extends TestCase
{
    public function test_it_reports_an_analyzer_error_under_its_own_code_and_keeps_the_phel_code(): void
    {
        $diagnostics = CompileErrorRule::besides([])->apply($this->analysis(
            $this->diagnostic(ErrorCode::MACRO_EXPANSION_ERROR, Diagnostic::SEVERITY_ERROR),
        ));

        self::assertCount(1, $diagnostics);
        self::assertSame(LintRuleCodes::COMPILE_ERROR, $diagnostics[0]->code);
        self::assertSame('PHEL005', $diagnostics[0]->errorCode);
        self::assertSame('PHEL005 message', $diagnostics[0]->message);
        self::assertSame([2, 13, 2, 34], [
            $diagnostics[0]->startLine,
            $diagnostics[0]->startCol,
            $diagnostics[0]->endLine,
            $diagnostics[0]->endCol,
        ]);
    }

    public function test_it_leaves_the_codes_a_dedicated_rule_promotes_to_that_rule(): void
    {
        $rule = CompileErrorRule::besides([
            new UnresolvedSymbolRule(),
            new ArityMismatchRule(),
            new UnresolvedReferRule(),
            new RedundantDoRule(),
        ]);

        $diagnostics = $rule->apply($this->analysis(
            $this->diagnostic(ErrorCode::UNDEFINED_SYMBOL, Diagnostic::SEVERITY_ERROR),
            $this->diagnostic(ErrorCode::ARITY_ERROR, Diagnostic::SEVERITY_ERROR),
            $this->diagnostic(ErrorCode::UNRESOLVED_REFER, Diagnostic::SEVERITY_ERROR),
            $this->diagnostic(ErrorCode::INVALID_SPECIAL_FORM, Diagnostic::SEVERITY_ERROR),
            $this->diagnostic(ErrorCode::BINDING_ERROR, Diagnostic::SEVERITY_ERROR),
        ));

        self::assertSame(
            ['PHEL007', 'PHEL008'],
            array_map(static fn(Diagnostic $d): ?string => $d->errorCode, $diagnostics),
        );
    }

    public function test_it_ignores_an_analyzer_finding_that_is_not_an_error(): void
    {
        $diagnostics = CompileErrorRule::besides([])->apply($this->analysis(
            $this->diagnostic(ErrorCode::TYPE_ERROR, Diagnostic::SEVERITY_WARNING),
        ));

        self::assertSame([], $diagnostics);
    }

    private function analysis(Diagnostic ...$semantic): FileAnalysis
    {
        return new FileAnalysis(
            uri: 'app.phel',
            namespace: 'app',
            source: '',
            forms: [],
            projectIndex: new ProjectIndex([], []),
            semanticDiagnostics: $semantic,
        );
    }

    private function diagnostic(ErrorCode $code, string $severity): Diagnostic
    {
        return new Diagnostic(
            code: $code->value,
            severity: $severity,
            message: $code->value . ' message',
            uri: 'app.phel',
            startLine: 2,
            startCol: 13,
            endLine: 2,
            endCol: 34,
            errorCode: $code->value,
        );
    }
}
