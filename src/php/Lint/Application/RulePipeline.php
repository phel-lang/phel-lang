<?php

declare(strict_types=1);

namespace Phel\Lint\Application;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;

use Throwable;

/**
 * Runs every registered rule against a `FileAnalysis` and rewrites the
 * severity on each produced diagnostic based on the configured
 * `RuleSettings`. Rules set to `off` or excluded by glob are skipped
 * entirely; rules that throw are isolated so one bad rule cannot kill
 * the whole run.
 */
final readonly class RulePipeline
{
    /**
     * @param list<LintRuleInterface> $rules
     */
    public function __construct(
        private array $rules,
    ) {}

    /**
     * @return list<Diagnostic>
     */
    public function run(FileAnalysis $analysis, RuleSettings $settings): array
    {
        $result = [];
        foreach ($this->rules as $rule) {
            $code = $rule->code();
            if (!$settings->isEnabled($code)) {
                continue;
            }

            if ($settings->isExcluded($code, $analysis->uri, $analysis->namespace)) {
                continue;
            }

            try {
                $diagnostics = $rule->apply($analysis);
            } catch (Throwable) {
                // Isolate a failing rule so the rest of the pipeline keeps running.
                continue;
            }

            $severity = $settings->severityFor($code);
            foreach ($diagnostics as $diagnostic) {
                $result[] = new Diagnostic(
                    code: $diagnostic->code,
                    severity: $severity,
                    message: $diagnostic->message,
                    uri: $diagnostic->uri,
                    startLine: $diagnostic->startLine,
                    startCol: $diagnostic->startCol,
                    endLine: $diagnostic->endLine,
                    endCol: $diagnostic->endCol,
                );
            }
        }

        return $result;
    }
}
