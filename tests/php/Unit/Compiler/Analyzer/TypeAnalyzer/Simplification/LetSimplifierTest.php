<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification\LetSimplifier;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class LetSimplifierTest extends TestCase
{
    public function test_drops_unused_pure_binding(): void
    {
        // (let [x 1 y 2] y) -> (let [y 2] y)
        $env = NodeEnvironment::empty()->withExpressionContext();
        $xBinding = $this->binding('x', new LiteralNode($env, 1));
        $yBinding = $this->binding('y', new LiteralNode($env, 2));
        $body = new DoNode($env, [], new LocalVarNode($env, $yBinding->getShadow()));
        $node = new LetNode($env, [$xBinding, $yBinding], $body, false);

        $simplified = new LetSimplifier()->simplify($node);

        self::assertCount(1, $simplified->getBindings());
        self::assertSame($yBinding, $simplified->getBindings()[0]);
    }

    public function test_keeps_unused_binding_with_impure_init(): void
    {
        // Init is a LocalVarNode whose containing CallNode isn't pure;
        // simulate via an empty DoNode (which the purity oracle treats
        // as impure since it's not in the fold whitelist).
        $env = NodeEnvironment::empty()->withExpressionContext();
        $impureInit = new DoNode($env, [], new LiteralNode($env, 1));
        $xBinding = $this->binding('x', $impureInit);
        $yBinding = $this->binding('y', new LiteralNode($env, 2));
        $body = new DoNode($env, [], new LocalVarNode($env, $yBinding->getShadow()));
        $node = new LetNode($env, [$xBinding, $yBinding], $body, false);

        $simplified = new LetSimplifier()->simplify($node);

        self::assertSame($node, $simplified);
    }

    public function test_keeps_loop_bindings_untouched(): void
    {
        // (loop [x 1] x) — even when init is pure, recur could rebind x
        $env = NodeEnvironment::empty()->withExpressionContext();
        $xBinding = $this->binding('x', new LiteralNode($env, 1));
        $body = new DoNode($env, [], new LocalVarNode($env, $xBinding->getShadow()));
        $node = new LetNode($env, [$xBinding], $body, true);

        $simplified = new LetSimplifier()->simplify($node);

        self::assertSame($node, $simplified);
    }

    public function test_returns_same_instance_when_all_bindings_used(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $xBinding = $this->binding('x', new LiteralNode($env, 1));
        $body = new DoNode($env, [], new LocalVarNode($env, $xBinding->getShadow()));
        $node = new LetNode($env, [$xBinding], $body, false);

        $simplified = new LetSimplifier()->simplify($node);

        self::assertSame($node, $simplified);
    }

    private function binding(string $name, mixed $init): BindingNode
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        return new BindingNode(
            $env,
            Symbol::create($name),
            Symbol::gen($name . '_'),
            $init,
        );
    }
}
