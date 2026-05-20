<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\Cache;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\CatchNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayPushNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectSetNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Ast\ReifyNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Compiler\Domain\Analyzer\Ast\TryNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;

/**
 * Collects the names of every `LocalVarNode` referenced anywhere inside
 * a subtree, including nested function bodies (a closure that captures a
 * local is still a real reference to that local).
 *
 * Used by `RecurEmitter` to decide whether a `recur` form can skip its
 * temp-variable shuffle: if no expression reads a recur param that an
 * earlier expression has already overwritten, the temps are unnecessary.
 *
 * Mirrors {@see BodyConstantScanner::children()} as a manual node-type
 * registry; any AST node added in the future that can contain a
 * `LocalVarNode` must be listed here, otherwise the recur optimisation
 * may emit incorrect direct assignments. When in doubt, leave the new
 * node out (the result is "always use temp vars", which is safe).
 */
final class LocalVarReferences
{
    /**
     * @return list<string> the `Symbol::getName()` of every referenced local
     */
    public static function collect(AbstractNode $node): array
    {
        $names = [];
        self::walk($node, $names);
        return $names;
    }

    /**
     * @param list<string> $names
     */
    private static function walk(AbstractNode $node, array &$names): void
    {
        if ($node instanceof LocalVarNode) {
            $names[] = $node->getName()->getName();
            return;
        }

        foreach (self::children($node) as $child) {
            self::walk($child, $names);
        }
    }

    /**
     * @return list<AbstractNode>
     */
    private static function children(AbstractNode $node): array
    {
        return match (true) {
            $node instanceof CallNode => [$node->getFn(), ...$node->getArguments()],
            $node instanceof ApplyNode => [$node->getFn(), ...$node->getArguments()],
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
            $node instanceof ReifyNode => [],
            default => [],
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
