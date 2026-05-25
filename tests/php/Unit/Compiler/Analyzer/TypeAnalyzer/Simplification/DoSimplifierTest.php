<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer\Simplification;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification\DoSimplifier;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class DoSimplifierTest extends TestCase
{
    public function test_drops_pure_literals_in_non_tail(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $node = new DoNode(
            $env,
            [new LiteralNode($env, 1), new LiteralNode($env, 2)],
            new LiteralNode($env, 3),
        );

        $simplified = new DoSimplifier()->simplify($node);

        self::assertSame([], $simplified->getStmts());
        self::assertSame(3, $this->literalValue($simplified->getRet()));
    }

    public function test_keeps_impure_calls_in_non_tail(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $println = $this->coreCall('println', [new LiteralNode($env, 'x')]);
        $node = new DoNode(
            $env,
            [new LiteralNode($env, 1), $println, new LiteralNode($env, 2)],
            new LiteralNode($env, 3),
        );

        $simplified = new DoSimplifier()->simplify($node);

        self::assertCount(1, $simplified->getStmts());
        self::assertSame($println, $simplified->getStmts()[0]);
    }

    public function test_preserves_tail_even_if_pure(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $ret = new LiteralNode($env, 42);
        $node = new DoNode($env, [], $ret);

        $simplified = new DoSimplifier()->simplify($node);

        self::assertSame($ret, $simplified->getRet());
    }

    public function test_returns_same_instance_when_nothing_to_drop(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $println = $this->coreCall('println', [new LiteralNode($env, 'x')]);
        $node = new DoNode($env, [$println], new LiteralNode($env, 1));

        $simplified = new DoSimplifier()->simplify($node);

        self::assertSame($node, $simplified);
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function coreCall(string $name, array $args): CallNode
    {
        $env = NodeEnvironment::empty()->withExpressionContext();

        return new CallNode(
            $env,
            new GlobalVarNode($env, CompilerConstants::PHEL_CORE_NAMESPACE, Symbol::create($name), Phel::map()),
            $args,
        );
    }

    private function literalValue(AbstractNode $node): mixed
    {
        self::assertInstanceOf(LiteralNode::class, $node);
        return $node->getValue();
    }
}
