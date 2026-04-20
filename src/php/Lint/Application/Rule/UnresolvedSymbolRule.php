<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Api\Transfer\Diagnostic;
use Phel\Compiler\Domain\Exceptions\ErrorCode;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;

/**
 * Promotes the analyzer's native `PHEL001` undefined-symbol diagnostic
 * into a lint-rule diagnostic so the standard severity/opt-out plumbing
 * applies. Reads from the shared `FileAnalysis::$semanticDiagnostics`
 * cache so the Parser + Analyzer only runs once per file.
 */
final readonly class UnresolvedSymbolRule implements LintRuleInterface
{
    public function code(): string
    {
        return RuleRegistry::UNRESOLVED_SYMBOL;
    }

    public function apply(FileAnalysis $analysis): array
    {
        $result = [];
        foreach ($analysis->semanticDiagnostics as $diagnostic) {
            if ($diagnostic->code !== ErrorCode::UNDEFINED_SYMBOL->value) {
                continue;
            }

            $result[] = new Diagnostic(
                code: $this->code(),
                severity: $diagnostic->severity,
                message: $diagnostic->message,
                uri: $diagnostic->uri,
                startLine: $diagnostic->startLine,
                startCol: $diagnostic->startCol,
                endLine: $diagnostic->endLine,
                endCol: $diagnostic->endCol,
            );
        }

        return $result;
    }
}
