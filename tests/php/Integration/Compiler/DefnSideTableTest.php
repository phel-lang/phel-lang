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

final class DefnSideTableTest extends TestCase
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

    public function test_defn_registers_fn_node_on_global_environment(): void
    {
        $this->compilerFacade->compile(
            '(defn identity-fn [x] x)',
            new CompileOptions(),
        );

        $node = self::$globalEnv->getDefFnNode('user', Symbol::create('identity-fn'));

        self::assertNotNull($node);
        self::assertCount(1, $node->getParams());
        self::assertSame('x', $node->getParams()[0]->getName());
    }

    public function test_def_with_literal_init_does_not_populate_side_table(): void
    {
        $this->compilerFacade->compile(
            '(def some-const 42)',
            new CompileOptions(),
        );

        self::assertNull(self::$globalEnv->getDefFnNode('user', Symbol::create('some-const')));
    }

    public function test_unknown_symbol_returns_null(): void
    {
        self::assertNull(self::$globalEnv->getDefFnNode('user', Symbol::create('never-defined')));
    }
}
