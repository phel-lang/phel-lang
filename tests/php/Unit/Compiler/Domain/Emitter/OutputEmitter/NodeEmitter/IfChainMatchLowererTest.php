<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\IfChainMatchLowerer;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class IfChainMatchLowererTest extends TestCase
{
    public function test_detects_two_arm_case_chain(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $shadow = Symbol::create('g_1');
        $binding = new BindingNode($env, Symbol::create('g'), $shadow, new LocalVarNode($env, Symbol::create('x')));

        $chain = new IfNode(
            $env,
            $this->equalityTest($env, $shadow, 1),
            new LiteralNode($env, 'one'),
            new IfNode(
                $env,
                $this->equalityTest($env, $shadow, 2),
                new LiteralNode($env, 'two'),
                new LiteralNode($env, 'other'),
            ),
        );
        $let = new LetNode($env, [$binding], new DoNode($env, [], $chain), false);

        $shape = IfChainMatchLowerer::analyse($let);

        self::assertNotNull($shape);
        self::assertCount(2, $shape['arms']);
        self::assertSame(1, $shape['arms'][0]['key']);
        self::assertSame('one', $shape['arms'][0]['expr']);
        self::assertSame('other', $shape['fallback']);
    }

    public function test_rejects_single_arm_chain(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $shadow = Symbol::create('g_2');
        $binding = new BindingNode($env, Symbol::create('g'), $shadow, new LocalVarNode($env, Symbol::create('x')));

        $chain = new IfNode(
            $env,
            $this->equalityTest($env, $shadow, 1),
            new LiteralNode($env, 'one'),
            new LiteralNode($env, 'other'),
        );
        $let = new LetNode($env, [$binding], new DoNode($env, [], $chain), false);

        self::assertNull(IfChainMatchLowerer::analyse($let));
    }

    public function test_rejects_loop_let(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $shadow = Symbol::create('g_3');
        $binding = new BindingNode($env, Symbol::create('g'), $shadow, new LiteralNode($env, 1));

        $chain = new IfNode(
            $env,
            $this->equalityTest($env, $shadow, 1),
            new LiteralNode($env, 'one'),
            new IfNode(
                $env,
                $this->equalityTest($env, $shadow, 2),
                new LiteralNode($env, 'two'),
                new LiteralNode($env, 'other'),
            ),
        );
        $let = new LetNode($env, [$binding], new DoNode($env, [], $chain), true);

        self::assertNull(IfChainMatchLowerer::analyse($let));
    }

    public function test_rejects_non_literal_arm_body(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $shadow = Symbol::create('g_4');
        $binding = new BindingNode($env, Symbol::create('g'), $shadow, new LiteralNode($env, 1));

        $chain = new IfNode(
            $env,
            $this->equalityTest($env, $shadow, 1),
            new LocalVarNode($env, Symbol::create('non-literal')),
            new IfNode(
                $env,
                $this->equalityTest($env, $shadow, 2),
                new LiteralNode($env, 'two'),
                new LiteralNode($env, 'other'),
            ),
        );
        $let = new LetNode($env, [$binding], new DoNode($env, [], $chain), false);

        self::assertNull(IfChainMatchLowerer::analyse($let));
    }

    private function equalityTest(NodeEnvironment $env, Symbol $shadow, int $key): CallNode
    {
        return new CallNode(
            $env,
            new GlobalVarNode(
                $env,
                CompilerConstants::PHEL_CORE_NAMESPACE,
                Symbol::create('='),
                Phel::map(),
            ),
            [
                new LocalVarNode($env, $shadow),
                new LiteralNode($env, $key),
            ],
        );
    }
}
