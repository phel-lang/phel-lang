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
use Throwable;

/**
 * Runtime parity coverage for the `(reduce f init [literals…])` fold.
 * The folded form returns a `LiteralNode`, so these tests cross-check
 * that the compile-time value matches what Phel's runtime `reduce`
 * would produce.
 */
final class ReduceFoldRuntimeTest extends TestCase
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

    public function test_reduce_plus_matches_runtime(): void
    {
        $result = $this->compilerFacade->eval('(reduce + 0 [1 2 3 4 5])', new CompileOptions());

        self::assertSame(15, $result);
    }

    public function test_reduce_mul_matches_runtime(): void
    {
        $result = $this->compilerFacade->eval('(reduce * 1 [2 3 4])', new CompileOptions());

        self::assertSame(24, $result);
    }

    public function test_reduce_max_matches_runtime(): void
    {
        $result = $this->compilerFacade->eval('(reduce max 0 [3 7 2 9 5])', new CompileOptions());

        self::assertSame(9, $result);
    }

    public function test_reduce_empty_vector_returns_init(): void
    {
        $result = $this->compilerFacade->eval('(reduce + 42 [])', new CompileOptions());

        self::assertSame(42, $result);
    }

    public function test_reduce_quot_divide_by_zero_keeps_runtime_behaviour(): void
    {
        // The folder refuses to fold when a step would hit divide-by-zero.
        // The runtime call must therefore still raise.
        $this->expectException(Throwable::class);

        $this->compilerFacade->eval('(reduce quot 10 [2 0 1])', new CompileOptions());
    }
}
