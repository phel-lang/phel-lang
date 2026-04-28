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
}
