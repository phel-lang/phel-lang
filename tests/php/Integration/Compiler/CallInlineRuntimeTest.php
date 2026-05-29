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

    public function test_call_to_pure_annotated_fn_inside_a_body_is_inlined(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        // `psquare` has a `let` body the rebaser cannot splice, so it is
        // never inlined as a callee — its call survives inside a body. The
        // `^:pure` tag still makes that surviving call a pure operator, so
        // `uses-pure` becomes inlinable; only its own frame disappears.
        $this->compilerFacade->eval('(defn ^:pure psquare [x] (let [y x] (* y y)))', $options);
        $this->compilerFacade->eval('(defn uses-pure [n] (+ (psquare n) 1))', $options);

        $php = $this->compilerFacade->compile('(uses-pure 5)', $options)->getPhpCode();

        self::assertStringNotContainsString('uses-pure', $php);
        self::assertStringContainsString('psquare', $php);
        self::assertSame(26, $this->compilerFacade->eval('(uses-pure 5)', $options));
    }

    public function test_unannotated_user_fn_in_body_keeps_dispatch(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        // Same non-inlinable `let`-body shape but without `^:pure`: the call
        // is impure to the detector, so `uses-plain`'s body is not provable
        // pure and `uses-plain` keeps dispatching.
        $this->compilerFacade->eval('(defn psquare-plain [x] (let [y x] (* y y)))', $options);
        $this->compilerFacade->eval('(defn uses-plain [n] (+ (psquare-plain n) 1))', $options);

        $php = $this->compilerFacade->compile('(uses-plain 5)', $options)->getPhpCode();

        self::assertStringContainsString('uses-plain', $php);
        self::assertSame(26, $this->compilerFacade->eval('(uses-plain 5)', $options));
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
