<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Shared\CompileOptions;
use Phel\Shared\Printer\Printer;
use PhelTest\Integration\Compiler\Fixture\LifetimeProbe;
use PhelTest\Integration\Compiler\Fixture\ProbeObservingMap;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;

use function range;
use function sprintf;
use function str_repeat;
use function str_replace;
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
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        new CompilerFacade()->eval(sprintf('(defn make-probe [] (new %s))', self::phelClassName(LifetimeProbe::class)), new CompileOptions());
    }

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
        yield 'extra arguments' => ['(fn [m a b] (update m :k (fn [v x y] (php/+ v x y)) a b))'];
        yield 'past the fixed arities' => ['(fn [m] (update m :k (fn [v a b c d] (php/+ v a b c d)) 1 2 3 4))'];
        yield 'several body forms' => ['(fn [m] (update m :k (fn [v] (println v) v)))'];
        yield 'php/max and an or' => ['(fn [m dt] (update m :t (fn [s] (php/max 0.0 (php/- (or s 0.0) dt)))))'];
        yield 'a keyword lookup' => ['(fn [m] (update m :k (fn [v] (:n v))))'];
        yield 'a global fn' => ['(fn [m] (update m :k (fn [v] (inc v))))'];
        yield 'a let and an if' => ['(fn [m] (update m :k (fn [v] (let [w (php/* v 2)] (if (php/> w 3) w 0)))))'];
        yield 'an if on a scalar-typed global fn' => ['(fn [m] (update m :k (fn [v] (if (nil? v) 0 v))))'];
        yield 'a let bound to a scalar-typed global fn' => ['(fn [m] (update m :k (fn [v] (let [n (count v)] (if (php/> n 1) v n)))))'];
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
        yield 'a condition map, whose assertion throws' => ['(fn [m] (update m :k (fn [v] {:pre [v]} v)))'];
        yield 'a lone pre map' => ['(fn [m] (update m :k (fn [v] {:pre [(int? v)]})))'];
        yield 'a class return tag' => ['(fn [m] (update m :k (fn ^DateTimeInterface [v] v)))'];
        yield 'func_get_args' => ['(fn [m] (update m :k (fn [v] (php/func_get_args))))'];
        yield 'extract' => ['(fn [m] (update m :k (fn [v] (php/extract (php-associative-array "v" 2)) v)))'];
        yield 'compact' => ['(fn [m flag] (update m :k (fn [v] (php/compact "flag"))))'];
        yield 'a php function outside the allowlist' => ['(fn [m] (update m :k (fn [v] (php/str_repeat v 2))))'];
        yield 'a method call' => ['(fn [m o] (update m :k (fn [v] (.format o v))))'];
        yield 'a nested fn, whose captures name the param' => ['(fn [m] (update m :k (fn [v] (fn [] v))))'];
        yield 'an inferred return type' => ['(fn [m] (update m :k (fn [v] {:b v})))'];
        yield 'several body forms in expression position' => ['(fn [m] [(update m :k (fn [v] (str v) v))])'];
        yield 'a collection with metadata' => ['(fn [m] (update m :k (fn [v] (php/count ^{:m v} [v]))))'];
        yield 'expression position' => ['(fn [m] [(update m :k (fn [v] (php/+ v 1)))])'];
        yield 'a call through a local' => ['(fn [m f] (update m :k (fn [v] (f v))))'];
        yield 'a loop' => ['(fn [m] (update m :k (fn [v] (loop [i 0] (if (php/< i v) (recur (php/+ i 1)) i)))))'];
        yield 'try' => ['(fn [m] (update m :k (fn [v] (try v (catch \\Exception e 0)))))'];
        yield 'php/ref' => ['(fn [m arr] (update m :k (fn [v] (php/preg_match "/a/" v (php/ref arr)))))'];
        yield 'yield' => ['(fn [m] (update m :k (fn [v] (php/yield v))))'];
        yield 'a destructured param, bound from a lookup' => ['(fn [m] (update m :k (fn [[v w]] (php/- w v))))'];
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
        yield '* overflows the value param' => ['(update {:a 4000000000} :a (fn [v] (* v v)))'];
        yield '+ overflows the value param' => ['(update {:a 9223372036854775807} :a (fn [v] (+ v 1)))'];
        yield '- overflows the value param' => ['(update {:a -9223372036854775807} :a (fn [v] (- v 10)))'];
        yield '* overflows an extra arg' => ['(update {:a 0} :a (fn [v x] (* x x)) 4000000000)'];
        yield '+ overflows an extra arg' => ['(update {:a 1} :a (fn [v x] (+ v x)) 9223372036854775807)'];
        yield '- overflows an extra arg' => ['(update {:a 0} :a (fn [v x] (- v x x)) 9223372036854775807)'];
        yield 'an if value' => ['(update {:a 1} :a (fn [v] (if (php/> v 0) (php/+ v 1) 0)))'];
        yield 'an or value' => ['(update {} :a (fn [v] (or v 0)))'];
        yield 'a let value' => ['(update {:a 1} :a (fn [v] (let [w (php/* v 2)] (php/+ w 1))))'];
        yield 'a value from a loop' => ['(update {:a 3} :a (fn [v] (loop [i 0 acc 0] (if (php/< i v) (recur (php/+ i 1) (php/+ acc i)) acc))))'];
        yield 'a try value' => ['(update {:a 1} :a (fn [v] (try (php/+ v 1) (catch \\Exception e 0))))'];
    }

    public function test_a_class_return_tag_still_rejects_at_both_levels(): void
    {
        $phel = '(update {:a 1} :a (fn ^DateTimeInterface [v] v))';

        self::assertStringStartsWith('threw ', $this->evalAt($phel, 0));
        self::assertSame($this->evalAt($phel, 0), $this->evalAt($phel, 2));
    }

    public function test_a_by_ref_global_fn_still_writes_only_the_closure_copy(): void
    {
        $this->compilerFacade->eval('(defn mutate! [^:by-ref arr] (php/aset arr 0 99))', new CompileOptions());
        $phel = '(let [arr (php-indexed-array 1)] (update {:a 1} :a (fn [v] (mutate! arr) v)) (php/aget arr 0))';

        self::assertSame('1', $this->evalAt($phel, 0));
        self::assertSame('1', $this->evalAt($phel, 2));
        self::assertStringContainsString('"update"', $this->compileInBuildMode('(fn [m arr] (update m :k (fn [v] (mutate! arr) v)))'));
    }

    public function test_an_inferred_return_type_still_rejects_an_overflow(): void
    {
        $phel = '(update {:a 1} :a (fn [v] (let [x 9223372036854775807] (php/+ x 1))))';

        self::assertSame($this->evalAt($phel, 0), $this->evalAt($phel, 2));
    }

    public function test_a_dynamic_global_rebound_to_a_by_ref_fn_writes_only_the_closure_copy(): void
    {
        // An impure body, or the call inliner would splice `touch` away first.
        $this->compilerFacade->eval('(defn ^:dynamic touch [arr] (php/print ""))', new CompileOptions());
        $this->compilerFacade->eval('(defn touch-by-ref [^:by-ref arr] (php/aset arr 0 99))', new CompileOptions());

        $phel = '(let [arr (php-indexed-array 1)] (binding [touch touch-by-ref] (update {:a 1} :a (fn [v] (touch arr) v))) (php/aget arr 0))';

        self::assertSame($this->evalAt($phel, 0), $this->evalAt($phel, 2));
        self::assertStringContainsString('"update"', $this->compileInBuildMode('(fn [m arr] (update m :k (fn [v] (touch arr) v)))'));
    }

    public function test_func_get_args_still_sees_the_fn_frame(): void
    {
        $phel = '(let [x 5] (update {:a 1} :a (fn [v] (vec (php/func_get_args)))))';

        self::assertSame($this->evalAt($phel, 0), $this->evalAt($phel, 2));
    }

    public function test_the_body_captures_locals_before_the_extra_args_run(): void
    {
        $phel = '(let [x 1] [(update {:a 0} :a (fn [v y] x) (php/= x 2)) x])';

        self::assertSame($this->evalAt($phel, 0), $this->evalAt($phel, 2));
        self::assertStringContainsString('"update"', $this->compileInBuildMode('(fn [x] [(update {:a 0} :a (fn [v y] x) (php/= x 2)) x])'));
    }

    #[DataProvider('providerCallerWrites')]
    public function test_target_and_key_still_run_in_the_caller_frame(string $phel): void
    {
        self::assertSame($this->evalAt($phel, 0), $this->evalAt($phel, 2));
    }

    public static function providerCallerWrites(): iterable
    {
        yield 'target, several body forms' => ['((fn [] (let [x nil] [(update (php/= x {:a 0}) :a (fn [v] (str v) v)) x])))'];
        yield 'target, one body form' => ['((fn [] (let [x nil] [(update (php/= x {:a 0}) :a (fn [v] v)) x])))'];
        yield 'key, several body forms' => ['((fn [] (let [x nil] [(update {:a 0} (php/= x :a) (fn [v] (str v) v)) x])))'];
        yield 'key, one body form' => ['((fn [] (let [x nil] [(update {:a 0} (php/= x :a) (fn [v] v)) x])))'];
        yield 'target in return position, several body forms' => ['((fn [] (let [x nil] (let [r (update (php/= x {:a 0}) :a (fn [v] (str v) v))] [r x]))))'];
    }

    #[DataProvider('providerMetadata')]
    public function test_collection_metadata_keeps_the_closure(string $phel): void
    {
        self::assertSame($this->evalAt($phel, 0), $this->evalAt($phel, 2));
    }

    public static function providerMetadata(): iterable
    {
        // No core fn wraps the literals: at -O2 the call inliner may splice
        // `identity` and bind its argument in an IIFE, which moves the
        // metadata's assignment out of the caller's frame on its own,
        // whether `update` lowers or not.
        yield 'an extra arg whose metadata assigns a captured local' => ['((fn [] (let [x 1] (update {:a 0} :a (fn [v y] x) ^{:touch (php/= x 2)} []))))'];
        yield 'body metadata reading the param' => ['(update {:a 1} :a (fn [v] (meta ^{:m v} [v])))'];
        yield 'body metadata assigning a captured local' => ['((fn [] (let [x 1] [(update {:a 0} :a (fn [v] (php/count ^{:m (php/= x 2)} [v]))) x])))'];
    }

    public function test_the_spliced_body_does_not_type_the_enclosing_params(): void
    {
        $defn = '(defn typed-probe [x] (update {:a 1} :a (fn [v] (let [ignored (php/. x "!")] v))))';
        $this->compilerFacade->eval($defn, new CompileOptions()->setOptimizationLevel(2));

        self::assertSame('{:a 1}', $this->evalAt('(typed-probe 42)', 2));
        self::assertStringNotContainsString('string $x', $this->compileInBuildMode($defn));
    }

    #[DataProvider('providerBodyLocalLifetimes')]
    public function test_a_body_local_is_released_before_the_assoc(string $body): void
    {
        $phel = sprintf(
            '(let [run (fn [t] (update t :a (fn [v] %s)))] (.-aliveAtPut (run (new %s))))',
            $body,
            self::phelClassName(ProbeObservingMap::class),
        );

        self::assertSame('0', $this->evalAt($phel, 0));
        self::assertSame('0', $this->evalAt($phel, 2));
        self::assertStringContainsString('"update"', $this->compileInBuildMode(sprintf('(fn [t] (update t :a (fn [v] %s)))', $body)));
    }

    public static function providerBodyLocalLifetimes(): iterable
    {
        yield 'a let in statement position' => ['(let [p (make-probe)] (php/is_null p)) v'];
        yield 'an alias of a fresh object' => ['(let [p (make-probe) q p] (php/is_null q)) v'];
        yield 'a let value' => ['(let [p (make-probe)] (if p v v))'];
        yield 'an if test in statement position' => ['(if (make-probe) 1 2) v'];
        yield 'an if test in the value' => ['(if (make-probe) v v)'];
        yield 'an and value' => ['(and (make-probe) v)'];
        yield 'an or test' => ['(if (or (make-probe) v) v v)'];
        yield 'a fresh object inside a vector' => ['(let [p [(make-probe)]] (php/is_null p)) v'];
    }

    public function test_an_expression_position_update_leaves_no_temporaries_in_the_frame(): void
    {
        $phel = '((fn [m] (let [r [(update m :a (fn [v] (php/+ v 1)))]] [r (php/count (php/get_defined_vars))])) {:a 1})';

        self::assertSame($this->evalAt($phel, 0), $this->evalAt($phel, 2));
    }

    public function test_an_update_in_a_try_body_keeps_the_closure(): void
    {
        $phel = '(fn [m] (try (update m :a (fn [v] (php/+ v 1))) (catch \\Exception e (php/get_defined_vars))))';

        self::assertStringContainsString('"update"', $this->compileInBuildMode($phel));
    }

    #[DataProvider('providerFrameProbes')]
    public function test_no_source_named_variable_is_left_in_the_frame(string $phel): void
    {
        self::assertSame($this->evalAt($phel, 0), $this->evalAt($phel, 2));
    }

    public static function providerFrameProbes(): iterable
    {
        yield 'expression position' => ['((fn [] [(update {:a 1} :a (fn [v] v)) (php/array_key_exists "v" (php/get_defined_vars))]))'];
        yield 'a binding init with an extra arg' => ['((fn [] (let [r (update {:a 1} :a (fn [v w] (php/+ v w)) 2)] [r (php/array_key_exists "w" (php/get_defined_vars))])))'];
    }

    public function test_a_macro_in_the_body_sees_the_same_env_at_both_levels(): void
    {
        $this->compilerFacade->eval('(defmacro env-keys [] `(quote ~(sort (map str (keys &env)))))', new CompileOptions());
        $phel = '(let [outer 1] (update {:a outer} :a (fn [v] (env-keys))))';

        self::assertSame($this->evalAt($phel, 0), $this->evalAt($phel, 2));
    }

    public function test_a_macro_in_a_declined_body_expands_once(): void
    {
        $this->compilerFacade->eval('(def expansions (atom 0))', new CompileOptions());
        $this->compilerFacade->eval('(defmacro counted [x] (swap! expansions inc) x)', new CompileOptions());

        $this->evalAt('(let [arr (php-indexed-array 1)] (update {:a 1} :a (fn [v] (php/aset arr 0 (counted v)) v)))', 2);

        self::assertSame(1, $this->compilerFacade->eval('(deref expansions)', new CompileOptions()));
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

    private static function phelClassName(string $class): string
    {
        return str_replace('\\', '.', $class);
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
