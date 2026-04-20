<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

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
        return SemanticDiagnosticPromoter::promote(
            $analysis,
            ErrorCode::UNDEFINED_SYMBOL->value,
            $this->code(),
        );
    }
}
