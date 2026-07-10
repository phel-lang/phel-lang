<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\CatchNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\MethodCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayPushNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNamedArgNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectSetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Compiler\Domain\Analyzer\Ast\TryNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;

/**
 * The child expression nodes of an analyzed AST node, as a single source of
 * truth shared by the subtree walkers whose maps are byte-identical:
 * {@see YieldDetector} and {@see ByRefLocalCollector}. Keeping one map removes
 * the drift those two policed only by a "keep in sync" comment — a drift that
 * had already dropped `SetNode`, silently costing `ByRefLocalCollector` a
 * by-reference capture.
 *
 * `of()` returns the child list for a known container, `[]` for a known leaf,
 * and `null` for a node whose shape is unrecognised — callers apply their own
 * policy for `null` (`ByRefLocalCollector` fails open via `?? []`, treating it
 * as a leaf; `YieldDetector` fails closed). It intentionally does not stop at
 * closure boundaries: that is per-walker traversal policy and stays in each
 * caller.
 *
 * The other two closure-bounded walkers (`Cache\BodyConstantScanner`,
 * `Analyzer\Ast\Reference\LocalVarReferences`) keep their own maps: theirs
 * genuinely diverge (e.g. `DefNode` also yields its meta, extra node types), so
 * they are not candidates for this shared map.
 */
final class NodeChildren
{
    /**
     * @return array<int, AbstractNode>|null
     */
    public static function of(AbstractNode $node): ?array
    {
        return match (true) {
            $node instanceof LiteralNode,
            $node instanceof LocalVarNode,
            $node instanceof GlobalVarNode,
            $node instanceof PhpVarNode => [],
            $node instanceof DoNode => [...$node->getStmts(), $node->getRet()],
            $node instanceof LetNode => [...$node->getBindings(), $node->getBodyExpr()],
            $node instanceof BindingNode => [$node->getInitExpr()],
            $node instanceof IfNode => [$node->getTestExpr(), $node->getThenExpr(), $node->getElseExpr()],
            $node instanceof CallNode => [$node->getFn(), ...$node->getArguments()],
            $node instanceof ApplyNode => [$node->getFn(), ...$node->getArguments()],
            $node instanceof VectorNode => $node->getArgs(),
            $node instanceof MapNode => $node->getKeyValues(),
            $node instanceof SetNode => $node->getValues(),
            $node instanceof ThrowNode => [$node->getExceptionExpr()],
            $node instanceof TryNode => [$node->getBody(), ...$node->getCatches(), ...self::maybe($node->getFinally())],
            $node instanceof CatchNode => [$node->getBody()],
            $node instanceof ForeachNode => [$node->getListExpr(), $node->getBodyExpr()],
            $node instanceof PhpObjectCallNode => [$node->getTargetExpr(), $node->getCallExpr()],
            $node instanceof PhpObjectSetNode => [$node->getLeftExpr(), $node->getRightExpr()],
            $node instanceof MethodCallNode => $node->getArgs(),
            $node instanceof PhpNewNode => [$node->getClassExpr(), ...$node->getArgs()],
            $node instanceof PhpNamedArgNode => [$node->getValueExpr()],
            $node instanceof RecurNode => $node->getExpressions(),
            $node instanceof SetVarNode => [$node->getSymbol(), $node->getValueExpr()],
            $node instanceof PhpArraySetNode => [$node->getArrayExpr(), ...$node->getAccessExprs(), $node->getValueExpr()],
            $node instanceof PhpArrayPushNode => [$node->getArrayExpr(), ...$node->getAccessExprs(), $node->getValueExpr()],
            $node instanceof PhpArrayUnsetNode => [$node->getArrayExpr(), ...$node->getAccessExprs()],
            $node instanceof PhpArrayGetNode => [$node->getArrayExpr(), ...$node->getAccessExprs()],
            $node instanceof DefNode => [$node->getInit()],
            default => null,
        };
    }

    /**
     * @return list<AbstractNode>
     */
    private static function maybe(?AbstractNode $node): array
    {
        return $node instanceof AbstractNode ? [$node] : [];
    }
}
