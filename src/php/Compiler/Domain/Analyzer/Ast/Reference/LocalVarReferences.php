<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast\Reference;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\CatchNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\MethodCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayPushNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectSetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PropertyOrConstantAccessNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Ast\ReifyNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Compiler\Domain\Analyzer\Ast\TryNode;
use Phel\Compiler\Domain\Analyzer\Ast\VarNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;

/**
 * Walks a subtree and returns the names of every `LocalVarNode` it
 * references, or `null` when it cannot prove the subtree's reference set
 * (an AST node type this scanner does not enumerate is treated as
 * potentially referencing anything, including recur params).
 *
 * `RecurEmitter` uses this to decide whether a `recur` form can skip
 * its temp-variable shuffle. `null` always forces temps, which is
 * always safe. So this scanner is opt-in per node type: new node types
 * added to the analyzer in the future will fall into the `default`
 * branch, return `null`, and the recur optimisation will conservatively
 * keep the temps until the new node is explicitly added here.
 */
final class LocalVarReferences
{
    /**
     * @return list<string>|null `null` means "subtree contains nodes we
     *                           don't recognise; assume worst case"
     */
    public static function collect(AbstractNode $node): ?array
    {
        $names = [];
        if (!self::walk($node, $names)) {
            return null;
        }

        return $names;
    }

    /**
     * @param list<string> $names
     */
    private static function walk(AbstractNode $node, array &$names): bool
    {
        if ($node instanceof LocalVarNode) {
            $names[] = $node->getName()->getName();
            return true;
        }

        $children = self::children($node);
        if ($children === null) {
            return false;
        }

        // Cannot be `array_all(...static fn... self::walk($child, $names))`:
        // PHP arrow functions capture by value, so `$names` inside the
        // closure would be a copy and accumulated references would be
        // silently lost.
        foreach ($children as $child) {
            if (!self::walk($child, $names)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, AbstractNode>|null `null` for nodes whose shape is not
     *                                       enumerated (forces a conservative
     *                                       "assume references exist" result)
     */
    private static function children(AbstractNode $node): ?array
    {
        return match (true) {
            // Leaf nodes that cannot contain a LocalVarNode.
            $node instanceof LiteralNode,
            $node instanceof QuoteNode,
            $node instanceof PhpVarNode,
            $node instanceof PhpClassNameNode,
            $node instanceof GlobalVarNode,
            $node instanceof VarNode,
            $node instanceof PropertyOrConstantAccessNode => [],

            $node instanceof CallNode => [$node->getFn(), ...$node->getArguments()],
            $node instanceof ApplyNode => [$node->getFn(), ...$node->getArguments()],
            $node instanceof MethodCallNode => $node->getArgs(),
            $node instanceof IfNode => [$node->getTestExpr(), $node->getThenExpr(), $node->getElseExpr()],
            $node instanceof DoNode => [...$node->getStmts(), $node->getRet()],
            $node instanceof LetNode => self::letChildren($node),
            $node instanceof RecurNode => $node->getExpressions(),
            $node instanceof TryNode => self::tryChildren($node),
            $node instanceof CatchNode => [$node->getBody()],
            $node instanceof ThrowNode => [$node->getExceptionExpr()],
            $node instanceof VectorNode => $node->getArgs(),
            $node instanceof MapNode => $node->getKeyValues(),
            $node instanceof SetNode => $node->getValues(),
            $node instanceof DefNode => [$node->getMeta(), $node->getInit()],
            $node instanceof SetVarNode => [$node->getSymbol(), $node->getValueExpr()],
            $node instanceof ForeachNode => [$node->getListExpr(), $node->getBodyExpr()],
            $node instanceof PhpNewNode => [$node->getClassExpr(), ...$node->getArgs()],
            $node instanceof PhpObjectCallNode => [$node->getTargetExpr(), $node->getCallExpr()],
            $node instanceof PhpObjectSetNode => [$node->getLeftExpr(), $node->getRightExpr()],
            $node instanceof PhpArrayGetNode => [$node->getArrayExpr(), ...$node->getAccessExprs()],
            $node instanceof PhpArraySetNode => [$node->getArrayExpr(), ...$node->getAccessExprs(), $node->getValueExpr()],
            $node instanceof PhpArrayPushNode => [$node->getArrayExpr(), ...$node->getAccessExprs(), $node->getValueExpr()],
            $node instanceof PhpArrayUnsetNode => [$node->getArrayExpr(), ...$node->getAccessExprs()],
            $node instanceof FnNode => [$node->getBody()],
            $node instanceof MultiFnNode => $node->getFnNodes(),
            // ReifyNode bodies are their own scope; opt-out of the optimisation.
            $node instanceof ReifyNode => null,
            default => null,
        };
    }

    /**
     * @return list<AbstractNode>
     */
    private static function letChildren(LetNode $node): array
    {
        $children = [];
        foreach ($node->getBindings() as $binding) {
            $children[] = $binding->getInitExpr();
        }

        $children[] = $node->getBodyExpr();
        return $children;
    }

    /**
     * @return list<AbstractNode>
     */
    private static function tryChildren(TryNode $node): array
    {
        $children = [$node->getBody(), ...$node->getCatches()];
        $finally = $node->getFinally();
        if ($finally instanceof AbstractNode) {
            $children[] = $finally;
        }

        return $children;
    }
}
