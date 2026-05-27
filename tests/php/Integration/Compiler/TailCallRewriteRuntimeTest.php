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
 * End-to-end coverage for the implicit-tail-call optimisation gated
 * behind `CompileOptions::setOptimizationLevel(2)`. The fixtures pick
 * recursion depths that would blow the default PHP call stack if the
 * rewrite did not fire.
 */
final class TailCallRewriteRuntimeTest extends TestCase
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

    public function test_self_tail_call_runs_at_depth_that_default_stack_blows(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval(
            '(defn count-down [n] (if (zero? n) :done (count-down (dec n))))',
            $options,
        );

        $result = $this->compilerFacade->eval('(count-down 50000)', $options);

        self::assertSame('done', $result->getName());
    }

    public function test_opt_level_zero_keeps_real_recursion(): void
    {
        $options = new CompileOptions();
        $this->compilerFacade->eval(
            '(defn count-down-shallow [n] (if (zero? n) :done (count-down-shallow (dec n))))',
            $options,
        );

        $result = $this->compilerFacade->eval('(count-down-shallow 50)', $options);

        self::assertSame('done', $result->getName());
    }

    public function test_tail_call_in_if_else_branch(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval(
            '(defn accumulate [n acc] (if (zero? n) acc (accumulate (dec n) (+ acc 1))))',
            $options,
        );

        $result = $this->compilerFacade->eval('(accumulate 20000 0)', $options);

        self::assertSame(20000, $result);
    }

    public function test_non_tail_call_stays_a_real_call(): void
    {
        // Non-tail self-reference: the recursive call's result is passed to
        // `+`, so the rewriter must leave it alone. We pick a small depth
        // because this *should* use the real stack.
        $options = new CompileOptions()->setOptimizationLevel(2);
        $this->compilerFacade->eval(
            '(defn factorial [n] (if (zero? n) 1 (* n (factorial (dec n)))))',
            $options,
        );

        $result = $this->compilerFacade->eval('(factorial 5)', $options);

        self::assertSame(120, $result);
    }
}
