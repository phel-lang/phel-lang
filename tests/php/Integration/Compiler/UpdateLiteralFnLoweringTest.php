<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Build\BuildFacade;
use Phel\Shared\CompileOptions;
use Phel\Shared\Printer\Printer;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;

use function range;
use function sprintf;
use function str_repeat;
use function strlen;

/**
 * An `update` whose function is a literal one-arity `fn` compiles to a read,
 * the body and one `assoc` (#3322). Every argument and the body are written
 * once, so nesting must grow the emitted code linearly, both threaded through
 * the target and nested inside the body. A shape that repeats its arguments
 * in both arms of a ternary doubles per level instead.
 *
 * Splicing is direct linking, so it only runs at optimization level 2: every
 * compile here asks for it, and each runtime case is checked against the
 * same source compiled at the default level, where `update` is called.
 */
final class UpdateLiteralFnLoweringTest extends AbstractCompilerRuntimeTestCase
{
    public function test_threaded_updates_grow_the_emitted_code_linearly(): void
    {
        $eight = strlen($this->compileThreaded(8));
        $sixteen = strlen($this->compileThreaded(16));

        self::assertLessThan(3 * $eight, $sixteen);
    }

    public function test_updates_nested_in_the_body_grow_the_emitted_code_linearly(): void
    {
        $eight = strlen($this->compileNestedInBody(8));
        $sixteen = strlen($this->compileNestedInBody(16));

        self::assertLessThan(3 * $eight, $sixteen);
    }

    #[DataProvider('providerLoweredShapes')]
    public function test_the_lowered_call_builds_no_closure_and_never_reaches_update(string $phel): void
    {
        $php = $this->compileInBuildMode($phel);

        self::assertStringNotContainsString('"update"', $php);
        self::assertStringNotContainsString('function($v', $php);
        self::assertStringContainsString('"assoc"', $php);
    }

    public static function providerLoweredShapes(): iterable
    {
        yield 'return position' => ['(fn [m] (update m :k (fn [v] (php/+ v 1))))'];
        yield 'expression position' => ['(fn [m] [(update m :k (fn [v] (php/+ v 1)))])'];
        yield 'extra arguments' => ['(fn [m a b] (update m :k (fn [v x y] [v x y]) a b))'];
        yield 'past the fixed arities' => ['(fn [m] (update m :k (fn [v a b c d] [v a b c d]) 1 2 3 4))'];
        yield 'a destructured param' => ['(fn [m] (update m :k (fn [[v w]] [w v])))'];
        yield 'several body forms' => ['(fn [m] (update m :k (fn [v] (println v) v)))'];
        yield 'a nested fn capturing the param' => ['(fn [m] (update m :k (fn [v] (fn [] v))))'];
        yield 'a map without conditions as the value' => ['(fn [m] (update m :k (fn [v] {:b v})))'];
    }

    public function test_the_default_level_keeps_the_runtime_call(): void
    {
        $php = $this->compileInBuildMode('(fn [m] (update m :k (fn [v] (php/+ v 1))))', new CompileOptions());

        self::assertStringContainsString('"update"', $php);
    }

    #[DataProvider('providerRuntimeShapes')]
    public function test_other_shapes_keep_the_runtime_call(string $phel): void
    {
        self::assertStringContainsString('"update"', $this->compileInBuildMode($phel));
    }

    public static function providerRuntimeShapes(): iterable
    {
        yield 'a fn value' => ['(fn [m f] (update m :k f))'];
        yield 'a rest param' => ['(fn [m] (update m :k (fn [v & xs] v)))'];
        yield 'several arities' => ['(fn [m] (update m :k (fn ([v] v) ([v x] x))))'];
        yield 'recur' => ['(fn [m] (update m :k (fn [v] (if v (recur nil) 1))))'];
        yield 'a named fn' => ['(fn [m] (update m :k (fn self [v] v)))'];
        yield 'a condition map' => ['(fn [m] (update m :k (fn [v] {:pre [v]} v)))'];
        yield 'a lone pre map' => ['(fn [m] (update m :k (fn [v] {:pre [(int? v)]})))'];
        yield 'a lone post map' => ['(fn [m] (update m :k (fn [v] {:post [true]})))'];
        yield 'a tagged param' => ['(fn [m] (update m :k (fn [^int v] v)))'];
        yield 'a tagged return' => ['(fn [m] (update m :k (fn ^int [v] v)))'];
        yield 'a param count the call does not fill' => ['(fn [m] (update m :k (fn [v x] v)))'];
        yield 'apply' => ['(fn [m] (apply update m :k [(fn [v] v)]))'];
        yield 'statement position' => ['(fn [m] (update m :k (fn [v] v)) nil)'];
        yield 'php/aset on an outer local' => ['(fn [m arr] (update m :k (fn [v] (php/aset arr 0 v) v)))'];
        yield 'an outer local passed to php' => ['(fn [m arr] (update m :k (fn [v] (php/sort arr) v)))'];
        yield 'an outer local passed to a method' => ['(fn [m o arr] (update m :k (fn [v] (.fill o arr) v)))'];
        yield 'php/= on an outer local' => ['(fn [m flag] [(update m :k (fn [v] (php/= flag 99))) flag])'];
        yield 'php/=& on an outer local' => ['(fn [m flag other] [(update m :k (fn [v] (php/=& flag other))) flag])'];
        yield 'php/ref' => ['(fn [m arr] (update m :k (fn [v] (php/preg_match "/a/" v (php/ref arr)))))'];
        yield 'yield' => ['(fn [m] (update m :k (fn [v] (php/yield v))))'];
    }

    #[DataProvider('providerRuntimeCases')]
    public function test_the_lowered_call_answers_what_the_runtime_call_answers(string $phel): void
    {
        $lowered = $this->evalAt($phel, 2);
        $runtime = $this->evalAt($phel, 0);

        self::assertSame($runtime, $lowered);
    }

    public static function providerRuntimeCases(): iterable
    {
        yield 'an existing key' => ['(update {:a 1} :a (fn [v] (php/+ v 1)))'];
        yield 'an absent key' => ['(update {:a 1} :b (fn [v] (nil? v)))'];
        yield 'an empty body' => ['(update {:a 1} :a (fn [v]))'];
        yield 'a nil target' => ['(update nil :a (fn [v] (if (nil? v) 1 v)))'];
        yield 'a vector index' => ['(update [1 2 3] 1 (fn [v] (php/* v 10)))'];
        yield 'the end index appends' => ['(update [1 2 3] 3 (fn [v] (nil? v)))'];
        yield 'past the end' => ['(update [1 2 3] 7 (fn [v] v))'];
        yield 'a transient' => ['(persistent (update (transient {:a 1}) :a (fn [v] (php/+ v 1))))'];
        yield 'metadata' => ['(meta (update (with-meta {:a 1} {:m true}) :a (fn [v] (php/+ v 1))))'];
        yield 'extra arguments' => ['(update {:a 1} :a (fn [v w x y z] [v w x y z]) 1 2 3 4)'];
        yield 'an extra arg reads the outer local a param shadows' => ['(let [x 100] (update {:a 1} :a (fn [v x y] [v x y]) 2 x))'];
        yield 'evaluation order' => ['(let [log (atom []) step (fn [t v] (swap! log conj t) v)] [(update (step :m {:a 1}) (step :k :a) (fn [v x] (step :body (php/+ v x))) (step :x 2)) (deref log)])'];
        yield 'a vector pattern' => ['(update {:p [1 2]} :p (fn [[a b]] (php/+ a b)))'];
        yield 'a map pattern' => ['(update {:p {:a 1 :b 2}} :p (fn [{:keys [a b]}] (php/+ a b)))'];
        yield 'the param shadows an outer local' => ['(let [v 100] [(update {:a 1} :a (fn [v] (php/+ v 1))) v])'];
        yield 'the body reads the target' => ['(let [m {:a 1 :b 2}] (update m :a (fn [v] (count m))))'];
        yield 'a nested fn captures the param' => ['((get (update {:a 1} :a (fn [v] (fn [] v))) :a))'];
        yield 'params named get and assoc' => ['(update {:a 1} :a (fn [get] (let [assoc get] (php/+ assoc 1))))'];
        yield 'a lone pre map' => ['(update {:a 1} :a (fn [v] {:pre [(int? v)]}))'];
        yield 'a map value' => ['(update {:a 1} :a (fn [v] {:b v}))'];
        yield 'aset on a captured array' => ['(let [arr (php-indexed-array 1 2)] (update {:a 1} :a (fn [v] (php/aset arr 0 99) v)) (php/aget arr 0))'];
        yield 'aset-in on a captured array' => ['(let [arr (php-indexed-array (php-indexed-array 1 2))] (update {:a 1} :a (fn [v] (php/aset-in arr [0 0] 99) v)) (php/aget-in arr [0 0]))'];
        yield 'php/= on a captured local' => ['(let [flag 1] [(update {:a 1} :a (fn [v] (php/= flag 99))) flag])'];
        yield 'php/=& on a captured local' => ['(let [flag 1 other 2] [(update {:a 1} :a (fn [v] (php/=& flag other))) flag])'];
        yield 'php/= on an offset of a captured array' => ['(let [arr (php-indexed-array 1 2)] (update {:a 1} :a (fn [v] (php/= (php/aget arr 0) 99))) (php/aget arr 0))'];
        yield 'sort on a captured array' => ['(let [arr (php-indexed-array 3 1 2)] (update {:a 1} :a (fn [v] (php/sort arr) v)) (php/aget arr 0))'];
        yield 'threaded' => ['[(-> {:a 1 :b 2} (update :a (fn [v] (php/+ v 1))) (update :b (fn [v] (php/- v 1))))]'];
        yield 'nested in the body' => ['[(update {:a {:b 1}} :a (fn [v] (update v :b (fn [v] (php/+ v 1)))))]'];
        yield 'a recur argument' => ['(loop [m {:a 0} i 0] (if (php/< i 5) (recur (update m :a (fn [v] (php/+ v 1))) (php/+ i 1)) m))'];
        yield 'a short fn' => ['(update {:a 1} :a #(php/+ % 1))'];
    }

    public function test_with_redefs_still_reaches_a_redefined_update(): void
    {
        $phel = '(let [orig update] (with-redefs [update (fn [& args] (if (= :probe (second args)) :redefined (apply orig args)))] (update {:probe 1} :probe (fn [v] (php/+ v 1)))))';

        self::assertSame(':redefined', $this->evalAt($phel, 0));
    }

    public function test_a_local_named_update_keeps_its_own_call(): void
    {
        $php = $this->compileInBuildMode('(fn [m] (let [update (fn [m k f] m)] (update m :k (fn [v] v))))');

        self::assertStringContainsString('($update_', $php);
        self::assertStringNotContainsString('"assoc"', $php);
    }

    private function compileThreaded(int $depth): string
    {
        $steps = '';
        foreach (range(1, $depth) as $i) {
            $steps .= sprintf(' (update :k%d (fn [v] (php/+ v %d)))', $i, $i);
        }

        return $this->compileInBuildMode(sprintf('(fn [m] [(-> m%s)])', $steps));
    }

    private function compileNestedInBody(int $depth): string
    {
        $open = str_repeat('(update v :k (fn [v] ', $depth);
        $close = str_repeat('))', $depth);

        return $this->compileInBuildMode(sprintf('(fn [m] [(update m :k (fn [v] %s(php/+ v 1)%s))])', $open, $close));
    }

    private function compileInBuildMode(string $phel, ?CompileOptions $options = null): string
    {
        BuildFacade::enableBuildMode();
        try {
            return $this->compilerFacade
                ->compile($phel, $options ?? new CompileOptions()->setOptimizationLevel(2))
                ->getPhpCode();
        } finally {
            BuildFacade::disableBuildMode();
        }
    }

    /**
     * The printed result, or the class of what it threw.
     */
    private function evalAt(string $phel, int $level): string
    {
        try {
            $result = $this->compilerFacade->eval($phel, new CompileOptions()->setOptimizationLevel($level));
        } catch (Throwable $throwable) {
            return 'threw ' . $throwable::class;
        }

        return Printer::readable()->print($result);
    }
}
