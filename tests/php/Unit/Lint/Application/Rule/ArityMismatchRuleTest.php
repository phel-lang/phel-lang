<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Rule\ArityMismatchRule;
use Phel\Shared\LintRuleCodes;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class ArityMismatchRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_wrong_arity_for_same_file_defn(): void
    {
        $rule = new ArityMismatchRule();
        $source = <<<PHEL
(ns user)

(defn add [x y] (+ x y))

(add 1 2 3)
PHEL;
        $analysis = $this->buildAnalysis($source);

        $diagnostics = $rule->apply($analysis);

        self::assertNotEmpty($diagnostics);
        self::assertSame(LintRuleCodes::ARITY_MISMATCH, $diagnostics[0]->code);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_allows_variadic_arity(): void
    {
        $rule = new ArityMismatchRule();
        $source = <<<PHEL
(ns user)

(defn vara [x & rest] x)

(vara 1 2 3 4)
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_correct_arity(): void
    {
        $rule = new ArityMismatchRule();
        $source = <<<PHEL
(ns user)

(defn add [x y] (+ x y))

(add 1 2)
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_php_method_sharing_a_local_fn_name(): void
    {
        $rule = new ArityMismatchRule();
        $source = <<<'PHEL'
(ns user)

(defn persistent [coll] coll)

(defn convert [coll] (php/-> coll (persistent)))
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_static_php_method_sharing_a_local_fn_name(): void
    {
        $rule = new ArityMismatchRule();
        $source = <<<'PHEL'
(ns user)

(defn empty [coll] coll)

(defn make [] (php/:: NodeEnvironment (empty)))
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_threaded_calls_whose_first_argument_is_spliced(): void
    {
        $rule = new ArityMismatchRule();
        $source = <<<'PHEL'
(ns user)

(defn bump [s path] s)

(defn run [s] (-> s (bump [:a]) (bump [:b])))
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_still_flags_a_wrong_arity_argument_inside_a_threaded_call(): void
    {
        $rule = new ArityMismatchRule();
        $source = <<<'PHEL'
(ns user)

(defn add [x y] (+ x y))

(defn run [s] (-> s (conj (add 1 2 3))))
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertCount(1, $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_treat_quote_and_syntax_quote_as_a_call_to_a_local_quote_fn(): void
    {
        $rule = new ArityMismatchRule();
        $source = <<<'PHEL'
(ns user)

(defn quote [s q] (str q s q))

(defmacro twice [x]
  `(do ~x ~x))

(println (twice 1))
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_quoted_list_headed_by_a_local_fn(): void
    {
        $rule = new ArityMismatchRule();
        $source = <<<'PHEL'
(ns user)

(defn add [a b] (+ a b))

(def form '(add 1))
(def nested '[(add 1) {:k (add)}])
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_still_flags_a_wrong_arity_call_unquoted_inside_a_syntax_quote(): void
    {
        $rule = new ArityMismatchRule();
        $source = <<<'PHEL'
(ns user)

(defn add [a b] (+ a b))

(defmacro build [] `(do (add 1) ~(add 1)))
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertCount(1, $rule->apply($analysis));
    }
}
