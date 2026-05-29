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
        $this->compilerFacade->eval('(defn shout [x] (str x "!"))', $options);

        // `str` is pure, so `shout` *is* inlinable — sanity that it runs.
        self::assertSame('hi!', $this->compilerFacade->eval('(shout "hi")', $options));

        // A genuinely side-effecting body must keep dispatching.
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
}
