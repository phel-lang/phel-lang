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
use Phel\Compiler\Domain\Analyzer\Ast\PhpNamedArgNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectSetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Ast\ReifyNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Compiler\Domain\Analyzer\Ast\TryNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;

use function array_any;

/**
 * Reports whether a node subtree contains a `(php/yield …)` in the current
 * function frame. PHP promotes any function containing `yield` to a generator,
 * so an emitter can only drop the IIFE around a construct (e.g. a return-context
 * `foreach`) once it knows the wrapper is not acting as that generator boundary:
 * eliding it would move the `yield` up to the enclosing fn, making *it* the
 * generator and deferring its pre-loop side-effects to first iteration.
 *
 * Traversal stops at closure boundaries (`FnNode`, `MultiFnNode`, `ReifyNode`):
 * a `yield` inside a nested closure makes that closure the generator, not the
 * frame we are in, so it does not force the wrapper here.
 *
 * Unlike {@see ByRefLocalCollector} (which shares the container arms but can
 * safely fall back to by-value on an unknown node) this detector fails *closed*:
 * a node whose shape we do not recognise is assumed to possibly yield, so the
 * wrapper is kept. Under-recognising only costs an unnecessary IIFE; it can
 * never silently misplace a generator boundary.
 */
final class YieldDetector
{
    public function containsYield(AbstractNode $node): bool
    {
        if ($node instanceof CallNode) {
            $fnNode = $node->getFn();
            if ($fnNode instanceof PhpVarNode && $fnNode->getName() === 'yield') {
                return true;
            }
        }

        if ($node instanceof FnNode || $node instanceof MultiFnNode || $node instanceof ReifyNode) {
            return false;
        }

        $children = $this->children($node);
        if ($children === null) {
            return true;
        }

        return array_any($children, fn(AbstractNode $child): bool => $this->containsYield($child));
    }

    /**
     * Child expression nodes to search: `[]` for a known leaf (no yield can
     * hide there), the child list for a known container, or `null` for a node
     * whose shape is unrecognised — the caller then fails closed. The container
     * arms mirror {@see ByRefLocalCollector::children()}; keep the two in sync.
     *
     * @return array<int, AbstractNode>|null
     */
    private function children(AbstractNode $node): ?array
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
            $node instanceof TryNode => [$node->getBody(), ...$node->getCatches(), ...$this->maybe($node->getFinally())],
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
    private function maybe(?AbstractNode $node): array
    {
        return $node instanceof AbstractNode ? [$node] : [];
    }
}
