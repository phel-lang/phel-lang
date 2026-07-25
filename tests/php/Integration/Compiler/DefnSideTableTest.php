<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;

final class DefnSideTableTest extends AbstractCompilerRuntimeTestCase
{
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
