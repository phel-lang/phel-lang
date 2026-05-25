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
        // `(let [x 1 y 2] y)` first drops `x` (unused, pure), then
        // inlines the only remaining binding `y` because the body is
        // `(do y)` and `y`'s init is a literal — collapses to `2`.
        $env = NodeEnvironment::empty()->withExpressionContext();
        $xBinding = $this->binding('x', new LiteralNode($env, 1));
        $yBinding = $this->binding('y', new LiteralNode($env, 2));
        $body = new DoNode($env, [], new LocalVarNode($env, $yBinding->getShadow()));
        $node = new LetNode($env, [$xBinding, $yBinding], $body, false);

        $simplified = new LetSimplifier()->simplify($node);

        self::assertInstanceOf(LiteralNode::class, $simplified);
        self::assertSame(2, $simplified->getValue());
    }

    public function test_keeps_unused_binding_with_impure_init(): void
    {
        // Impure init for `x` blocks the drop; `y`'s pure-literal init
        // would normally inline but with `x` still present we keep the
        // `LetNode` shape and only let the inline pass touch the tail.
        $env = NodeEnvironment::empty()->withExpressionContext();
        $impureInit = new DoNode($env, [], new LiteralNode($env, 1));
        $xBinding = $this->binding('x', $impureInit);
        $yBinding = $this->binding('y', new LiteralNode($env, 2));
        $body = new DoNode($env, [], new LocalVarNode($env, $yBinding->getShadow()));
        $node = new LetNode($env, [$xBinding, $yBinding], $body, false);

        $simplified = new LetSimplifier()->simplify($node);

        self::assertInstanceOf(LetNode::class, $simplified);
        self::assertCount(1, $simplified->getBindings());
        self::assertSame($xBinding, $simplified->getBindings()[0]);
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

    public function test_inlines_single_use_literal_binding(): void
    {
        // (let [x 5] x) — collapses to the literal `5`.
        $env = NodeEnvironment::empty()->withExpressionContext();
        $xBinding = $this->binding('x', new LiteralNode($env, 5));
        $body = new DoNode($env, [], new LocalVarNode($env, $xBinding->getShadow()));
        $node = new LetNode($env, [$xBinding], $body, false);

        $simplified = new LetSimplifier()->simplify($node);

        self::assertInstanceOf(LiteralNode::class, $simplified);
        self::assertSame(5, $simplified->getValue());
    }

    public function test_does_not_inline_when_init_is_not_literal(): void
    {
        // Init is an impure `DoNode` (not a literal): inline path skips.
        $env = NodeEnvironment::empty()->withExpressionContext();
        $init = new DoNode($env, [], new LiteralNode($env, 5));
        $xBinding = $this->binding('x', $init);
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
