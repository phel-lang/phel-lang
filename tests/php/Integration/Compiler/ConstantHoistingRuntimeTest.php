<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end check that pure collection literals inside a fn body are
 * hoisted to a per-fn static cache: two invocations must return the same
 * persistent value instance (identity, not just equality).
 */
final class ConstantHoistingRuntimeTest extends TestCase
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

    public function test_pure_vector_literal_is_shared_across_calls(): void
    {
        /** @var callable $fn */
        $fn = $this->compilerFacade->eval('(fn [] [1 2 3])', new CompileOptions());

        $a = $fn();
        $b = $fn();

        self::assertSame($a, $b, 'pure vector literal must be cached across fn calls');
    }

    public function test_pure_map_literal_is_shared_across_calls(): void
    {
        /** @var callable $fn */
        $fn = $this->compilerFacade->eval('(fn [] {:a 1 :b 2})', new CompileOptions());

        $a = $fn();
        $b = $fn();

        self::assertSame($a, $b, 'pure map literal must be cached across fn calls');
    }

    public function test_pure_set_literal_is_shared_across_calls(): void
    {
        /** @var callable $fn */
        $fn = $this->compilerFacade->eval('(fn [] #{1 2 3})', new CompileOptions());

        $a = $fn();
        $b = $fn();

        self::assertSame($a, $b, 'pure set literal must be cached across fn calls');
    }

    public function test_impure_vector_still_allocates_per_call(): void
    {
        // The argument depends on the parameter so the literal is not
        // pure and the optimization must skip it.
        /** @var callable $fn */
        $fn = $this->compilerFacade->eval('(fn [x] [x 2 3])', new CompileOptions());

        $a = $fn(1);
        $b = $fn(1);

        self::assertEquals($a, $b);
        self::assertNotSame($a, $b, 'literals depending on parameters must not be cached');
    }

    public function test_two_calls_with_different_args_keep_cache_unchanged(): void
    {
        /** @var callable $fn */
        $fn = $this->compilerFacade->eval('(fn [x] [1 2 3])', new CompileOptions());

        $first = $fn('ignored');
        $second = $fn(42);

        self::assertSame($first, $second);
    }

    public function test_repeated_keyword_dedups_to_one_slot_without_changing_runtime(): void
    {
        // `:k` appears three times in the body. The slots dedup to a single
        // `$__phel_const_0`, but the runtime result must be unchanged: each
        // keyword accessor still reads the right value from the map.
        $php = $this->compilerFacade
            ->compile('(fn [m] [(:k m) (:k m) (:k m)])', new CompileOptions())
            ->getPhpCode();

        self::assertStringContainsString('static $__phel_const_0;', $php);
        self::assertStringNotContainsString('$__phel_const_1', $php);

        /** @var callable $fn */
        $fn = $this->compilerFacade->eval('(fn [m] [(:k m) (:k m) (:k m)])', new CompileOptions());
        /** @var PersistentVectorInterface $result */
        $result = $fn(Phel::map(Keyword::create('k'), 7));

        self::assertSame(7, $result[0]);
        self::assertSame(7, $result[1]);
        self::assertSame(7, $result[2]);
    }

    public function test_distinct_keywords_keep_distinct_slots(): void
    {
        $php = $this->compilerFacade
            ->compile('(fn [m] [(:a m) (:b m) (:a m)])', new CompileOptions())
            ->getPhpCode();

        self::assertStringContainsString('static $__phel_const_0, $__phel_const_1;', $php);
        self::assertStringNotContainsString('$__phel_const_2', $php);
    }

    public function test_nested_fn_has_independent_static_scope(): void
    {
        /** @var callable $outer */
        $outer = $this->compilerFacade->eval(
            '(fn [] (fn [] [1 2 3]))',
            new CompileOptions(),
        );

        $innerA = $outer();
        $innerB = $outer();

        // Each call to the outer fn returns a fresh inner closure; their
        // static caches are independent, but each inner cache is shared
        // across its own invocations.
        $innerAFirst = $innerA();
        $innerASecond = $innerA();
        self::assertSame($innerAFirst, $innerASecond);

        $innerBFirst = $innerB();
        self::assertEquals($innerAFirst, $innerBFirst);
    }
}
