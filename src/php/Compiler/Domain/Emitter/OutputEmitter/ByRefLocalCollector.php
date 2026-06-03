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
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\MethodCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayPushNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectSetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpRefNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Ast\ReifyNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Compiler\Domain\Analyzer\Ast\TryNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;

use function array_values;

/**
 * Collects the shadow names of locals marked `(php/ref x)` inside a node
 * subtree, so an emitter that wraps the subtree in an IIFE can capture those
 * locals with `use(&$x)` rather than by value (otherwise a by-reference PHP
 * parameter writes into the closure copy and the result is lost).
 *
 * Traversal stops at closure boundaries (`FnNode`, `MultiFnNode`, `ReifyNode`):
 * a `php/ref` inside a user closure binds against that closure's own capture,
 * not the wrapping IIFE, so forcing the outer capture by reference would not
 * help. Unknown node types are treated as leaves; under-collecting only leaves
 * the pre-existing by-value behaviour untouched, never a wrong reference.
 */
final class ByRefLocalCollector
{
    /**
     * @return list<string> de-duplicated shadow names of by-reference locals
     */
    public function collect(AbstractNode $node): array
    {
        $names = [];
        $this->walk($node, $names);

        return array_values(array_unique($names));
    }

    /**
     * @param list<string> $names
     */
    private function walk(AbstractNode $node, array &$names): void
    {
        if ($node instanceof PhpRefNode) {
            $names[] = $node->getName()->getName();
            return;
        }

        if ($node instanceof FnNode || $node instanceof MultiFnNode || $node instanceof ReifyNode) {
            return;
        }

        foreach ($this->children($node) as $child) {
            $this->walk($child, $names);
        }
    }

    /**
     * @return list<AbstractNode>
     */
    private function children(AbstractNode $node): array
    {
        return match (true) {
            $node instanceof DoNode => [...$node->getStmts(), $node->getRet()],
            $node instanceof LetNode => [...$node->getBindings(), $node->getBodyExpr()],
            $node instanceof BindingNode => [$node->getInitExpr()],
            $node instanceof IfNode => [$node->getTestExpr(), $node->getThenExpr(), $node->getElseExpr()],
            $node instanceof CallNode => [$node->getFn(), ...$node->getArguments()],
            $node instanceof ApplyNode => [$node->getFn(), ...$node->getArguments()],
            $node instanceof VectorNode => $node->getArgs(),
            $node instanceof MapNode => $node->getKeyValues(),
            $node instanceof ThrowNode => [$node->getExceptionExpr()],
            $node instanceof TryNode => [$node->getBody(), ...$node->getCatches(), ...$this->maybe($node->getFinally())],
            $node instanceof CatchNode => [$node->getBody()],
            $node instanceof ForeachNode => [$node->getListExpr(), $node->getBodyExpr()],
            $node instanceof PhpObjectCallNode => [$node->getTargetExpr(), $node->getCallExpr()],
            $node instanceof PhpObjectSetNode => [$node->getLeftExpr(), $node->getRightExpr()],
            $node instanceof MethodCallNode => $node->getArgs(),
            $node instanceof PhpNewNode => [$node->getClassExpr(), ...$node->getArgs()],
            $node instanceof RecurNode => $node->getExpressions(),
            $node instanceof SetVarNode => [$node->getSymbol(), $node->getValueExpr()],
            $node instanceof PhpArraySetNode => [$node->getArrayExpr(), ...$node->getAccessExprs(), $node->getValueExpr()],
            $node instanceof PhpArrayPushNode => [$node->getArrayExpr(), ...$node->getAccessExprs(), $node->getValueExpr()],
            $node instanceof PhpArrayUnsetNode => [$node->getArrayExpr(), ...$node->getAccessExprs()],
            $node instanceof PhpArrayGetNode => [$node->getArrayExpr(), ...$node->getAccessExprs()],
            $node instanceof DefNode => [$node->getInit()],
            default => [],
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
