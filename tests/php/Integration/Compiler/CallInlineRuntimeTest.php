<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end coverage for the call inliner gated behind
 * `CompileOptions::setOptimizationLevel(2)` (issue #2135). A call to a
 * single-expression pure `defn` is spliced at the call site instead of
 * dispatching through the resolved `AbstractFn`.
 */
final class CallInlineRuntimeTest extends TestCase
{
    private static GlobalEnvironmentInterface $globalEnv;

    private CompilerFacade $compilerFacade;

    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        $globalEnv = GlobalEnvironmentSingleton::initializeNew();
        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );
        self::$globalEnv = $globalEnv;
    }

    protected function setUp(): void
    {
        $this->compilerFacade = new CompilerFacade();
        self::$globalEnv->setNs('user');
        Symbol::resetGen();

        // Phel compiles/evals by executing forms, so side-effecting callees
        // (e.g. `php/printf`) emit to stdout during these cases. Capture it so
        // it never leaks into the test runner output.
        ob_start();
    }

    protected function tearDown(): void
    {
        ob_end_clean();
    }

    public function test_literal_call_folds_to_a_constant(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval('(defn my-inc [x] (+ x 1))', $options);

        $result = $this->compilerFacade->eval('(my-inc 5)', $options);

        self::assertSame(6, $result);
    }

    public function test_symbolic_call_is_inlined_skipping_the_callee_frame(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval('(defn my-double [x] (* x 2))', $options);

        $php = $this->compilerFacade->compile('(let [y 10] (my-double y))', $options)->getPhpCode();

        // The callee dispatch is gone; only the spliced `*` op remains.
        self::assertStringNotContainsString('my-double', $php);
        self::assertSame(20, $this->compilerFacade->eval('(let [y 10] (my-double y))', $options));
    }

    public function test_shadowed_caller_local_is_not_captured(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval('(defn add1 [x] (+ x 1))', $options);

        // The caller binds `x` to 99; the param `x` must resolve to the
        // argument 7, not the caller local, so the result is 8 not 100.
        $result = $this->compilerFacade->eval('(let [x 99] (add1 7))', $options);

        self::assertSame(8, $result);
    }

    public function test_side_effecting_callee_is_not_inlined(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);

        // `str` is not in the purity allowlist, so `shout` is NOT inlined;
        // the call keeps dispatching and still returns the right value.
        $this->compilerFacade->eval('(defn shout [x] (str x "!"))', $options);
        $shoutPhp = $this->compilerFacade->compile('(shout "hi")', $options)->getPhpCode();
        self::assertStringContainsString('shout', $shoutPhp, 'non-allowlisted callee should still dispatch');
        self::assertSame('hi!', $this->compilerFacade->eval('(shout "hi")', $options));

        // A genuinely side-effecting body must keep dispatching too.
        $this->compilerFacade->eval('(defn log-it [x] (php/printf "%d" x))', $options);
        $php = $this->compilerFacade->compile('(log-it 5)', $options)->getPhpCode();

        self::assertStringContainsString('log-it', $php, 'side-effecting callee should still dispatch');
    }

    public function test_recursive_callee_is_not_inlined(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval(
            '(defn sum-to [n acc] (if (zero? n) acc (recur (dec n) (+ acc n))))',
            $options,
        );

        $result = $this->compilerFacade->eval('(sum-to 5 0)', $options);

        self::assertSame(15, $result);
    }

    public function test_opt_level_zero_keeps_the_dispatch(): void
    {
        $options = new CompileOptions();
        $this->compilerFacade->eval('(defn my-inc0 [x] (+ x 1))', $options);

        $php = $this->compilerFacade->compile('(my-inc0 5)', $options)->getPhpCode();

        self::assertStringContainsString('my-inc0', $php);
        self::assertSame(6, $this->compilerFacade->eval('(my-inc0 5)', $options));
    }

    public function test_impure_argument_is_evaluated_exactly_once(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval('(def inline-calls (atom 0))', $options);
        $this->compilerFacade->eval('(defn inline-bump [] (swap! inline-calls inc))', $options);
        $this->compilerFacade->eval('(defn inline-twice [x] (+ x x))', $options);

        // `inline-bump` is impure, so it is bound to a single `let` even
        // though the body uses the param twice: one effect, result 1 + 1.
        $result = $this->compilerFacade->eval('(inline-twice (inline-bump))', $options);

        self::assertSame(2, $result);
        self::assertSame(1, $this->compilerFacade->eval('(deref inline-calls)', $options));
    }

    public function test_impure_unused_argument_still_runs_once(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval('(def inline-calls2 (atom 0))', $options);
        $this->compilerFacade->eval('(defn inline-bump2 [] (swap! inline-calls2 inc))', $options);
        $this->compilerFacade->eval('(defn inline-ignore [x] 42)', $options);

        // The param is unused, but the impure arg must still evaluate once.
        $result = $this->compilerFacade->eval('(inline-ignore (inline-bump2))', $options);

        self::assertSame(42, $result);
        self::assertSame(1, $this->compilerFacade->eval('(deref inline-calls2)', $options));
    }

    public function test_impure_arguments_keep_left_to_right_order(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval('(def inline-log (atom []))', $options);
        $this->compilerFacade->eval('(defn inline-mark [k] (swap! inline-log conj k) k)', $options);
        $this->compilerFacade->eval('(defn inline-pair [a b] (+ a b))', $options);

        $result = $this->compilerFacade->eval('(inline-pair (inline-mark 1) (inline-mark 2))', $options);

        self::assertSame(3, $result);
        self::assertTrue($this->compilerFacade->eval('(= [1 2] (deref inline-log))', $options));
    }

    public function test_pure_multi_use_argument_is_inlined_correctly(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval('(defn inline-sq [x] (* x x))', $options);

        $php = $this->compilerFacade->compile('(let [y 5] (inline-sq (+ y 1)))', $options)->getPhpCode();

        // The callee frame is gone and the (pure, twice-used) argument is
        // bound once instead of duplicating the addition.
        self::assertStringNotContainsString('inline-sq', $php);
        self::assertSame(36, $this->compilerFacade->eval('(let [y 5] (inline-sq (+ y 1)))', $options));
    }

    public function test_defn_returning_a_vector_is_inlined(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval('(defn make-pair [a b] [a b])', $options);

        $php = $this->compilerFacade->compile('(make-pair 1 2)', $options)->getPhpCode();

        self::assertStringNotContainsString('make-pair', $php);
        self::assertTrue($this->compilerFacade->eval('(= [1 2] (make-pair 1 2))', $options));
    }

    public function test_defn_returning_a_map_is_inlined(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval('(defn make-box [v] {:val v})', $options);

        $php = $this->compilerFacade->compile('(make-box 5)', $options)->getPhpCode();

        self::assertStringNotContainsString('make-box', $php);
        self::assertTrue($this->compilerFacade->eval('(= {:val 5} (make-box 5))', $options));
    }

    public function test_pure_annotated_callee_with_unprovable_body_is_inlined(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        // `(php/intval s)` is not a structurally pure node, but the `^:pure`
        // annotation asserts it is, so the call is inlined (frame gone).
        $this->compilerFacade->eval('(defn ^:pure to-int [s] (php/intval s))', $options);

        $php = $this->compilerFacade->compile('(to-int "42")', $options)->getPhpCode();

        self::assertStringNotContainsString('to-int', $php);
        self::assertSame(42, $this->compilerFacade->eval('(to-int "42")', $options));
    }

    public function test_let_bodied_pure_annotated_fn_is_inlined(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        // A `let`-bodied `^:pure` callee is now spliced wherever it is
        // called: `psquare` inlines into `uses-pure`'s body, then
        // `uses-pure` itself inlines into the call site — both frames gone.
        $this->compilerFacade->eval('(defn ^:pure psquare [x] (let [y x] (* y y)))', $options);
        $this->compilerFacade->eval('(defn uses-pure [n] (+ (psquare n) 1))', $options);

        $php = $this->compilerFacade->compile('(uses-pure 5)', $options)->getPhpCode();

        self::assertStringNotContainsString('uses-pure', $php);
        self::assertStringNotContainsString('psquare', $php);
        self::assertSame(26, $this->compilerFacade->eval('(uses-pure 5)', $options));
    }

    public function test_let_bodied_structurally_pure_fn_is_inlined_without_annotation(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        // No `^:pure` tag: the detector proves the `let` body pure
        // structurally (locals + `*` are side-effect-free), so the
        // let-bodied callee inlines on its own.
        $this->compilerFacade->eval('(defn psquare-plain [x] (let [y x] (* y y)))', $options);

        $php = $this->compilerFacade->compile('(psquare-plain 6)', $options)->getPhpCode();

        self::assertStringNotContainsString('psquare-plain', $php);
        self::assertSame(36, $this->compilerFacade->eval('(psquare-plain 6)', $options));
    }

    public function test_let_bodied_impure_unannotated_fn_keeps_dispatch(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        // A `let` body that is not provably pure (here `str`, not in the
        // purity allowlist) and carries no `^:pure` tag keeps dispatching.
        $this->compilerFacade->eval('(defn shout-plain [x] (let [y x] (str y "!")))', $options);

        $php = $this->compilerFacade->compile('(shout-plain "hi")', $options)->getPhpCode();

        self::assertStringContainsString('shout-plain', $php);
        self::assertSame('hi!', $this->compilerFacade->eval('(shout-plain "hi")', $options));
    }

    public function test_let_bodied_callee_with_nested_let_if_or_is_inlined(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        // The issue's motivating example: a hot per-iteration helper whose
        // body binds one intermediate and branches on it. `^:pure` plus the
        // new `let` rebasing means the frame disappears at every call site.
        $this->compilerFacade->eval(
            '(defn ^:pure cell [grid x y] (let [row (get grid y)] (if (nil? row) :wall (or (get row x) :wall))))',
            $options,
        );

        $php = $this->compilerFacade->compile('(let [g {0 {0 :a}}] (cell g 0 0))', $options)->getPhpCode();
        self::assertStringNotContainsString('cell', $php);

        // Every branch preserves the runtime semantics.
        self::assertTrue($this->compilerFacade->eval('(= :a (let [g {0 {0 :a}}] (cell g 0 0)))', $options));
        self::assertTrue($this->compilerFacade->eval('(= :wall (cell {} 5 5))', $options));
        self::assertTrue($this->compilerFacade->eval('(= :wall (cell {0 {}} 9 0))', $options));
    }

    public function test_let_bodied_inline_preserves_sequential_binding_semantics(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        // Sequential bindings where a later init reads an earlier shadow and
        // the param is reused: the fresh-shadow remap must keep them wired.
        $this->compilerFacade->eval('(defn ^:pure step [n] (let [a (+ n 1) b (* a 2)] (+ a b)))', $options);

        $php = $this->compilerFacade->compile('(step 3)', $options)->getPhpCode();

        self::assertStringNotContainsString('step', $php);
        // a = 4, b = 8 => 12
        self::assertSame(12, $this->compilerFacade->eval('(step 3)', $options));
    }

    public function test_let_bodied_inline_evaluates_impure_arg_once(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval('(def let-calls (atom 0))', $options);
        $this->compilerFacade->eval('(defn let-bump [] (swap! let-calls inc))', $options);
        // Param used twice inside the let body; an impure arg must bind once.
        $this->compilerFacade->eval('(defn ^:pure let-twice [x] (let [y x] (+ y x)))', $options);

        $result = $this->compilerFacade->eval('(let-twice (let-bump))', $options);

        self::assertSame(2, $result);
        self::assertSame(1, $this->compilerFacade->eval('(deref let-calls)', $options));
    }

    public function test_multi_arity_defn_inlines_the_matching_arity(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval('(defn poly ([x] (+ x 1)) ([x y] (+ x y)))', $options);

        $php1 = $this->compilerFacade->compile('(poly 5)', $options)->getPhpCode();
        $php2 = $this->compilerFacade->compile('(poly 2 3)', $options)->getPhpCode();

        self::assertStringNotContainsString('poly', $php1);
        self::assertStringNotContainsString('poly', $php2);
        self::assertSame(6, $this->compilerFacade->eval('(poly 5)', $options));
        self::assertSame(5, $this->compilerFacade->eval('(poly 2 3)', $options));
    }

    public function test_multi_arity_defn_does_not_inline_the_variadic_arity(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval('(defn polyv ([x] (+ x 1)) ([x & xs] x))', $options);

        // The fixed 1-arity call inlines; the variadic arity keeps dispatch.
        $fixed = $this->compilerFacade->compile('(polyv 5)', $options)->getPhpCode();
        $variadic = $this->compilerFacade->compile('(polyv 5 6 7)', $options)->getPhpCode();

        self::assertStringNotContainsString('polyv', $fixed);
        self::assertStringContainsString('polyv', $variadic);
        self::assertSame(6, $this->compilerFacade->eval('(polyv 5)', $options));
        self::assertSame(5, $this->compilerFacade->eval('(polyv 5 6 7)', $options));
    }
}
