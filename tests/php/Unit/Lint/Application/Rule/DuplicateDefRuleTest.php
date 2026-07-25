<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Rule\DuplicateDefRule;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class DuplicateDefRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_symbol_defined_twice(): void
    {
        $rule = new DuplicateDefRule();
        $analysis = $this->buildAnalysis("(ns fixtures\\dup)\n(defn handle [x] x)\n(defn handle [x] (inc x))\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertSame(RuleRegistry::DUPLICATE_DEF, $diagnostics[0]->code);
        self::assertStringContainsString('handle', $diagnostics[0]->message);
        self::assertSame(3, $diagnostics[0]->startLine);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_forward_declaration_followed_by_its_definition(): void
    {
        $rule = new DuplicateDefRule();
        $analysis = $this->buildAnalysis(
            "(ns fixtures\\fwd)\n(declare later-fn)\n(defn caller [x] (later-fn x))\n(defn later-fn [x] (inc x))\n",
        );

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_private_definitions(): void
    {
        $rule = new DuplicateDefRule();
        $analysis = $this->buildAnalysis("(ns fixtures\\priv)\n(def- a 1)\n(def- b 2)\n(defn- c [] (+ a b))\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_private_definition_repeated(): void
    {
        $rule = new DuplicateDefRule();
        $analysis = $this->buildAnalysis("(ns fixtures\\priv2)\n(def- a 1)\n(def- a 2)\n");

        self::assertCount(1, $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_the_same_name_across_different_def_flavours(): void
    {
        $rule = new DuplicateDefRule();
        $analysis = $this->buildAnalysis("(ns fixtures\\mixed)\n(def thing 1)\n(defn thing [] 2)\n");

        self::assertCount(1, $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_defonce_which_tolerates_re_evaluation(): void
    {
        $rule = new DuplicateDefRule();
        $analysis = $this->buildAnalysis("(ns fixtures\\once)\n(defonce state (atom {}))\n(defonce state (atom {}))\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_local_shadowing_a_global_name(): void
    {
        $rule = new DuplicateDefRule();
        $analysis = $this->buildAnalysis("(ns fixtures\\local)\n(def x 1)\n(defn f [] (let [x 2] x))\n");

        self::assertSame([], $rule->apply($analysis));
    }
}
