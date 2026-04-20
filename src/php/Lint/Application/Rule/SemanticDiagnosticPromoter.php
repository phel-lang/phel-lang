<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lint\Domain\FileAnalysis;

/**
 * Re-tags an analyzer-level diagnostic (`PHEL001`, `PHEL002`, ...) as a
 * rule-level diagnostic so the standard severity/opt-out plumbing applies.
 *
 * Used by rules whose signal already flows through the shared
 * `FileAnalysis::$semanticDiagnostics` cache: they just pick the codes
 * they care about and rewrite the `code` field to the rule identifier.
 */
final class SemanticDiagnosticPromoter
{
    /**
     * @return list<Diagnostic>
     */
    public static function promote(
        FileAnalysis $analysis,
        string $semanticCode,
        string $ruleCode,
    ): array {
        $result = [];
        foreach ($analysis->semanticDiagnostics as $diagnostic) {
            if ($diagnostic->code !== $semanticCode) {
                continue;
            }

            $result[] = new Diagnostic(
                code: $ruleCode,
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
