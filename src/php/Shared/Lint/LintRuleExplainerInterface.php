<?php

declare(strict_types=1);

namespace Phel\Shared\Lint;

/**
 * @internal
 * The prose behind each lint rule, for a command outside Lint that explains
 * codes: `phel explain` lives in Run, and Lint already depends on Run
 */
interface LintRuleExplainerInterface
{
    /**
     * What one lint rule reports, as `phel lint` prints its code, with or
     * without the `phel/` prefix. Null for an unknown rule.
     *
     * @return array{code: string, title: string, summary: string, example: string, fix: string}|null
     */
    public function explainRule(string $code): ?array;

    /**
     * @return list<array{code: string, title: string, summary: string, example: string, fix: string}>
     */
    public function ruleExplanations(): array;
}
