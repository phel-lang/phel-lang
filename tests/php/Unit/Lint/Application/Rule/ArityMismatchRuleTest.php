<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Rule\ArityMismatchRule;
use Phel\Shared\LintRuleCodes;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_still_checks_a_local_fn_named_like_a_pattern_macro(): void
    {
        $rule = new ArityMismatchRule();
        $source = <<<'PHEL'
(ns user)

(defn match [re s] s)

(match "x")
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertCount(1, $rule->apply($analysis));
    }

    #[DataProvider('providerLiteralHeadCallWithWrongArity')]
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_literal_head_called_with_a_wrong_number_of_arguments(string $call, string $message): void
    {
        $rule = new ArityMismatchRule();
        $analysis = $this->buildAnalysis("(ns user)\n\n(defn f [m] {$call})");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertSame(LintRuleCodes::ARITY_MISMATCH, $diagnostics[0]->code);
        self::assertSame($message, $diagnostics[0]->message);
    }

    public static function providerLiteralHeadCallWithWrongArity(): iterable
    {
        yield 'keyword with three args' => ['(:k m 1 2)', "Wrong number of arguments for ':k'. Expected 1 or 2, given 3."];
        yield 'keyword with no args' => ['(:k)', "Wrong number of arguments for ':k'. Expected 1 or 2, given 0."];
        yield 'vector with a not-found arg' => ['([1 2] 0 1)', "Wrong number of arguments for 'vector'. Expected 1, given 2."];
        yield 'vector with no args' => ['([1 2])', "Wrong number of arguments for 'vector'. Expected 1, given 0."];
        yield 'map with a not-found arg' => ['({:a 1} :b 2)', "Wrong number of arguments for 'map'. Expected 1, given 2."];
        yield 'map with no args' => ['({:a 1})', "Wrong number of arguments for 'map'. Expected 1, given 0."];
        yield 'set with a not-found arg' => ['(#{1} 1 2)', "Wrong number of arguments for 'set'. Expected 1, given 2."];
        yield 'set with no args' => ['(#{1})', "Wrong number of arguments for 'set'. Expected 1, given 0."];
        yield 'fn with too many args' => ['((fn [x] x) 1 2)', "Wrong number of arguments for 'fn'. Expected 1, given 2."];
        yield 'fn with no args' => ['((fn [x] x))', "Wrong number of arguments for 'fn'. Expected 1, given 0."];
        yield 'short fn with too few args' => ['(#(+ %1 %2) 1)', "Wrong number of arguments for 'fn'. Expected 2, given 1."];
        yield 'multi-arity fn above its largest arity' => ['((fn ([x] x) ([x y z] y)) 1 2 3 4)', "Wrong number of arguments for 'fn'. Expected 1 to 3, given 4."];
        yield 'variadic fn below its fixed arity' => ['((fn [x y & r] x) 1)', "Wrong number of arguments for 'fn'. Expected 2+, given 1."];
        yield 'vector as a single-arity defn body' => ['([1 2] 0 m)', "Wrong number of arguments for 'vector'. Expected 1, given 2."];
    }

    #[DataProvider('providerLiteralHeadCallWithAcceptedArity')]
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_allows_a_literal_head_called_with_an_accepted_number_of_arguments(string $source): void
    {
        $rule = new ArityMismatchRule();
        $analysis = $this->buildAnalysis("(ns user)\n\n" . $source);

        self::assertSame([], $rule->apply($analysis));
    }

    public static function providerLiteralHeadCallWithAcceptedArity(): iterable
    {
        yield 'keyword lookup' => ['(defn f [m] [(:k m) (:k m 1)])'];
        yield 'vector, map and set lookup' => ['(defn f [m] [([1 2] 0) ({:a 1} :a) (#{1} m)])'];
        yield 'fn literals' => ['(defn f [m] [((fn [x] x) 1) ((fn [x & r] x) 1 2 3) (#(+ % 1) 1) (#(apply + %&)) ((fn ([x] x) ([x y] y)) 1 2)])'];
        yield 'quoted forms' => ["(def a '(:k 1 2 3))\n(def b '[([1 2] 0 1) {:k (#{1})}])"];
        yield 'syntax-quoted template' => ['(defmacro m [x] `(:k ~x 1 2))'];
        yield 'threaded keyword' => ['(defn f [m] (-> m (:k)))'];
        yield 'ns clauses' => ['(ns app (:require [phel.string :as s] [phel.json :as j] [phel.test :refer [deftest]]))'];
        yield 'case grouped test constants' => ['(defn f [x] (case x (:a :b :c :d) 1 ([1 2] 0 1) 2 3))'];
        yield 'match patterns' => ['(defn f [x] (match [x] [(:or 1 2 3)] :ok [([a b] :as v)] v :else nil))'];
        yield 'multi-arity defn' => ['(defn f "doc" ([x] (f x 1)) ([x y] (prn x) [x y]))'];
        yield 'multi-arity defmacro' => ['(defmacro m ([x] x) ([x y] x y))'];
        yield 'multi-arity defmethod with a vector dispatch value' => ["(defmulti d :t)\n(defmethod d [:a :b] ([x] x) ([x y] x y))"];
        yield 'multi-arity fn' => ['(def f (fn ([x] x) ([x y] x y)))'];
        yield 'multi-arity letfn binding' => ['(defn f [] (letfn [(g ([x] x) ([x y] x y))] (g 1)))'];
        yield 'multi-arity method of extend-type' => ["(defprotocol P (m [this] [this x]))\n(extend-type :string P (m ([this] this) ([this x] this x)))"];
    }
}
