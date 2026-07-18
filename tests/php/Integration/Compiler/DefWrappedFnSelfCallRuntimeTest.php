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
 * A self-reference inside a fn that is NOT the direct init of its def (e.g.
 * `(def f (wrap (fn ...)))`) must route through the registry: the fn compiles
 * to a plain closure there, so the `$this` self-call shortcut of direct
 * `(def f (fn ...))` inits would call an unrelated object.
 */
final class DefWrappedFnSelfCallRuntimeTest extends TestCase
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

    public function test_recursion_through_wrapped_fn_init(): void
    {
        $result = $this->compilerFacade->eval(
            '(def wrapped-fact (identity (fn [n] (if (<= n 1) 1 (* n (wrapped-fact (- n 1)))))))'
            . ' (wrapped-fact 5)',
            new CompileOptions(),
        );

        self::assertSame(120, $result);
    }

    public function test_direct_fn_init_keeps_self_call_shortcut(): void
    {
        $compiled = $this->compilerFacade->compile(
            '(def direct-fact (fn [n] (if (<= n 1) 1 (* n (direct-fact (- n 1))))))',
            new CompileOptions(),
        )->getPhpCode();

        self::assertStringContainsString('$this(', $compiled);
    }

    public function test_wrapped_fn_init_routes_self_call_through_registry(): void
    {
        $compiled = $this->compilerFacade->compile(
            '(def wrapped-loop (identity (fn [n] (wrapped-loop n))))',
            new CompileOptions(),
        )->getPhpCode();

        self::assertStringNotContainsString('$this(', $compiled);
        self::assertStringContainsString(Phel::class . '::getDefinition("user", "wrapped-loop")', $compiled);
    }
}
