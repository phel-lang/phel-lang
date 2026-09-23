<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;

/**
 * Re-homes a node analysed in return position into expression position, so
 * a fn body's value can be spliced as an argument ({@see UpdateLiteralFnLowering}).
 *
 * The context is baked into each node's environment and decides whether it
 * emits `return …;`. Only the node itself carries the return context: the
 * children of a call or a collection literal were analysed as expressions
 * already, and an `if` hands its context to both branches, which are
 * re-homed in turn. A `let` or a `do` re-homed as-is keeps its body in return
 * position, which is how either emits in expression position: as an IIFE
 * that returns. Any other node is wrapped in such a `do`.
 *
 * @internal
 */
final readonly class ExpressionContextRebuilder
{
    public function rebuild(AbstractNode $node): AbstractNode
    {
        $env = $node->getEnv();
        if ($env->isContext(NodeEnvironment::CONTEXT_EXPRESSION)) {
            return $node;
        }

        $env = $env->withExpressionContext();
        $loc = $node->getStartSourceLocation();

        return match (true) {
            $node instanceof CallNode => new CallNode($env, $node->getFn(), $node->getArguments(), $loc),
            $node instanceof LocalVarNode => new LocalVarNode($env, $node->getName(), $loc),
            $node instanceof LiteralNode => new LiteralNode($env, $node->getValue(), $loc),
            $node instanceof VectorNode => new VectorNode($env, $node->getArgs(), $loc, $node->getMeta()),
            $node instanceof MapNode => new MapNode($env, $node->getKeyValues(), $loc, $node->getLiteralMeta()),
            $node instanceof SetNode => new SetNode($env, $node->getValues(), $loc, $node->getMeta()),
            $node instanceof IfNode => new IfNode(
                $env,
                $node->getTestExpr(),
                $this->rebuild($node->getThenExpr()),
                $this->rebuild($node->getElseExpr()),
                $loc,
            ),
            $node instanceof LetNode && !$node->isLoop() => new LetNode($env, $node->getBindings(), $node->getBodyExpr(), false, $loc),
            $node instanceof DoNode && $node->getStmts() === [] => $this->rebuild($node->getRet()),
            $node instanceof DoNode => new DoNode($env, $node->getStmts(), $node->getRet(), $loc),
            default => new DoNode($env, [], $node, $loc),
        };
    }
}
