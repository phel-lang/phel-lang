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

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_treat_a_for_collection_expression_as_a_binding(): void
    {
        $rule = new UnusedBindingRule();
        // Read pairwise, `coll` looks like a bound-and-unused name.
        $analysis = $this->buildAnalysis("(for [[k v] :pairs coll] [k v])\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_an_unused_for_binding(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(for [x :in [1 2]] 42)\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString("'x'", $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_for_binding_used_only_by_a_modifier(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(for [x :in [1 2] :when (even? x)] 42)\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_for_let_binding_used_by_a_later_value(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(for [x :in [1 2] :let [a x b (inc a)]] b)\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_an_unused_for_let_binding(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(for [x :in [1 2] :let [a 1]] x)\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString("'a'", $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_reduce_accumulator_used_in_the_body(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(for [x :in [1 2] :reduce [acc 0]] (+ acc x))\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_an_unused_reduce_accumulator(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(for [x :in [1 2] :reduce [acc 0]] x)\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString("'acc'", $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_an_unused_dofor_binding(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(dofor [x :in [1 2]] (println \"hi\"))\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString("'x'", $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_treat_a_foreach_collection_expression_as_a_binding(): void
    {
        $rule = new UnusedBindingRule();
        // Read pairwise, the trailing `coll` looks like a bound-and-unused name.
        $analysis = $this->buildAnalysis("(foreach [x coll] (println x))\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_an_unused_foreach_binding(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(foreach [x [1 2]] (println \"hi\"))\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString("'x'", $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_only_the_unused_half_of_a_three_element_foreach_head(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(foreach [k v {:a 1}] (println v))\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString("'k'", $diagnostics[0]->message);
    }
}
