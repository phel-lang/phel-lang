<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Rule\ShadowedBindingRule;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class ShadowedBindingRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_shadowed_binding_in_nested_let(): void
    {
        $rule = new ShadowedBindingRule();
        $analysis = $this->buildAnalysis("(let [x 1] (let [x 2] x))\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertSame(RuleRegistry::SHADOWED_BINDING, $diagnostics[0]->code);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_distinct_bindings(): void
    {
        $rule = new ShadowedBindingRule();
        $analysis = $this->buildAnalysis("(let [x 1] (let [y 2] (+ x y)))\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_fn_param_shadowing_outer_binding(): void
    {
        $rule = new ShadowedBindingRule();
        $analysis = $this->buildAnalysis("(let [x 1] (fn [x] x))\n");

        self::assertNotEmpty($rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_treat_a_for_collection_expression_as_a_binding(): void
    {
        $rule = new ShadowedBindingRule();
        // `coll` is the collection being iterated, not a name the head binds.
        $analysis = $this->buildAnalysis("(let [coll [1 2]] (for [x :in coll] x))\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_treat_a_destructured_for_collection_as_a_binding(): void
    {
        $rule = new ShadowedBindingRule();
        $analysis = $this->buildAnalysis("(let [form {:a 1}] (for [[k v] :pairs form] [k v]))\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_for_binding_that_shadows_an_outer_local(): void
    {
        $rule = new ShadowedBindingRule();
        $analysis = $this->buildAnalysis("(let [x 1] (for [x :in [1 2]] x))\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString("'x'", $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_for_let_modifier_binding_that_shadows(): void
    {
        $rule = new ShadowedBindingRule();
        $analysis = $this->buildAnalysis("(let [y 1] (for [x :in [1 2] :let [y 2]] [x y]))\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString("'y'", $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_for_reduce_accumulator_that_shadows(): void
    {
        $rule = new ShadowedBindingRule();
        $analysis = $this->buildAnalysis("(let [acc 1] (for [x :in [1 2] :reduce [acc 0]] (+ acc x)))\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString("'acc'", $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_understands_dofor_heads_too(): void
    {
        $rule = new ShadowedBindingRule();
        $clean = $this->buildAnalysis("(let [coll [1 2]] (dofor [x :in coll] (println x)))\n");
        $shadowed = $this->buildAnalysis("(let [x 1] (dofor [x :in [1 2]] (println x)))\n");

        self::assertSame([], $rule->apply($clean));
        self::assertCount(1, $rule->apply($shadowed));
    }
}
