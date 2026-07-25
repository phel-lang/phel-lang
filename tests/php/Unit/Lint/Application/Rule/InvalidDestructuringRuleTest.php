<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Rule\InvalidDestructuringRule;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class InvalidDestructuringRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_odd_binding_vector(): void
    {
        $rule = new InvalidDestructuringRule();
        $analysis = $this->buildAnalysis("(let [x 1 y] x)\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertSame(RuleRegistry::INVALID_DESTRUCTURING, $diagnostics[0]->code);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_invalid_variadic_marker_in_fn(): void
    {
        $rule = new InvalidDestructuringRule();
        $analysis = $this->buildAnalysis("(fn [a & b c] a)\n");

        self::assertNotEmpty($rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_accepts_well_formed_variadic_fn(): void
    {
        $rule = new InvalidDestructuringRule();
        $analysis = $this->buildAnalysis("(fn [a & rest] a)\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_accepts_a_bare_trailing_variadic_marker(): void
    {
        $rule = new InvalidDestructuringRule();
        $analysis = $this->buildAnalysis("(defmacro comment [&])\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_accepts_a_for_head_which_is_not_a_pair_vector(): void
    {
        $rule = new InvalidDestructuringRule();
        $analysis = $this->buildAnalysis("(for [x :in [1 2 3]] x)\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_accepts_a_for_head_with_destructuring_modifiers_and_options(): void
    {
        $rule = new InvalidDestructuringRule();
        $analysis = $this->buildAnalysis(
            "(for [[k v] :pairs m :when v :reduce [acc []]] (conj acc k))\n",
        );

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_accepts_a_dofor_head(): void
    {
        $rule = new InvalidDestructuringRule();
        $analysis = $this->buildAnalysis("(dofor [x :in [1 2 3]] (println x))\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_for_head_missing_its_expression(): void
    {
        $rule = new InvalidDestructuringRule();
        $analysis = $this->buildAnalysis("(for [x :in] x)\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertSame(RuleRegistry::INVALID_DESTRUCTURING, $diagnostics[0]->code);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_for_head_with_a_dangling_modifier(): void
    {
        $rule = new InvalidDestructuringRule();
        $analysis = $this->buildAnalysis("(for [x :in xs :when] x)\n");

        self::assertCount(1, $rule->apply($analysis));
    }
}
