<?php

declare(strict_types=1);

namespace Phel\Lint\Application;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Domain\Exception\LintRuleException;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Shared\Api\Diagnostic;

use Throwable;

/**
 * Runs every registered rule against a `FileAnalysis` and rewrites the
 * severity on each produced diagnostic based on the configured
 * `RuleSettings`. Rules set to `off` or excluded by glob are skipped
 * entirely.
 *
 * A rule that throws is isolated but never silenced: the pipeline records a
 * `phel/internal-error` diagnostic naming it and carries on with the rest, so
 * one bad rule neither kills the run nor lets it report the file as clean.
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
            } catch (Throwable $throwable) {
                // Isolate the failing rule, but report it: the rest of the
                // pipeline keeps running and the run cannot come back clean.
                $result[] = $this->internalError($code, $analysis->uri, $throwable);

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

    /**
     * Severity is fixed at `error` instead of read from `RuleSettings`: the
     * configured severity grades a finding about the linted code, and a crash
     * is a finding about the linter. Honouring a `:warning` here would leave
     * `phel lint` exiting 0 with the rule's real findings missing, which is
     * the silent pass this diagnostic exists to prevent. The escape hatch is
     * the explicit one, setting the rule to `:off`.
     *
     * The crash has no source location, so it is anchored at the start of the
     * file it happened on; the message carries the rule code and chains the
     * original throwable.
     */
    private function internalError(string $ruleCode, string $uri, Throwable $throwable): Diagnostic
    {
        return new Diagnostic(
            code: RuleRegistry::INTERNAL_ERROR,
            severity: Diagnostic::SEVERITY_ERROR,
            message: LintRuleException::ruleCrashed($ruleCode, $throwable)->getMessage(),
            uri: $uri,
            startLine: 1,
            startCol: 1,
            endLine: 1,
            endCol: 1,
        );
    }
}
