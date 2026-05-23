<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Rule\UnusedBindingRule;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class UnusedBindingRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_unused_let_binding(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(let [x 1] 42)\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertSame(RuleRegistry::UNUSED_BINDING, $diagnostics[0]->code);
        self::assertStringContainsString("'x'", $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_used_binding(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(let [x 1] x)\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_ignores_underscore_bindings(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(let [_ 1] 42)\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_binding_consumed_only_by_later_sibling(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(let [n 1 msg (str n)] msg)\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_binding_consumed_through_chain_of_siblings(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(let [a 1 b (inc a) c (inc b)] c)\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_binding_referenced_only_in_earlier_sibling(): void
    {
        $rule = new UnusedBindingRule();
        // `tail` is never read; the only reference appears in `head`,
        // which is bound BEFORE `tail`, so it cannot resolve to it.
        $analysis = $this->buildAnalysis("(let [head 1 tail 2] head)\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString("'tail'", $diagnostics[0]->message);
    }
}
