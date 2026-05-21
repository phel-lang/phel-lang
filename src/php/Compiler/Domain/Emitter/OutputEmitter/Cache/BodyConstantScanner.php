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
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
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
use Phel\Compiler\Domain\Emitter\OutputEmitter\CallSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitter\GlobalCallTarget;
use Phel\Lang\Keyword;

/**
 * Walks a fn body looking for *outermost* pure collection literals plus
 * `Keyword` literals so the emitter can hoist them to a per-fn `static`
 * cache, and reserves a per-fn `static $__phel_call_N` slot for each
 * global-fn call site when call-site caching is enabled. Stops at nested
 * function boundaries (FnNode / MultiFnNode / ReifyNode method bodies)
 * because each has its own static scope.
 */
final readonly class BodyConstantScanner
{
    public function scan(AbstractNode $body, ConstantScope $scope, bool $cacheCalls = false): void
    {
        $this->walk($body, $scope, $cacheCalls);
    }

    private function walk(AbstractNode $node, ConstantScope $scope, bool $cacheCalls): void
    {
        // Nested function bodies own their own scope; skip.
        if ($node instanceof FnNode || $node instanceof MultiFnNode || $node instanceof ReifyNode) {
            return;
        }

        if ($this->isCacheableCollection($node) || $this->isCacheableKeyword($node)) {
            $scope->reserve($node);
            return;
        }

        if ($cacheCalls
            && $node instanceof CallNode
            && GlobalCallTarget::isGlobalFnCall($node)
            && !CallSpecialization::isSpecialized($node)
        ) {
            $scope->reserveCallSlot($node);
            // Fall through so child args are still scanned for nested literals/calls.
        }

        foreach ($this->children($node) as $child) {
            $this->walk($child, $scope, $cacheCalls);
        }
    }

    private function isCacheableCollection(AbstractNode $node): bool
    {
        if (!$node instanceof VectorNode && !$node instanceof MapNode && !$node instanceof SetNode) {
            return false;
        }

        // Skip empty literals: \Phel::vector([]) already returns an empty
        // singleton via the type factory, so caching adds no win.
        if ($this->isEmpty($node)) {
            return false;
        }

        return PureLiteralDetector::isPure($node);
    }

    /**
     * A `LiteralNode` whose value is a {@see Keyword} is identity-shared
     * via the interpreter's intern pool, but every call site still hits
     * `\Phel::keyword("…")` afresh. Caching the resolved instance in a
     * per-fn `static` slot skips the intern-pool hash on subsequent calls.
     */
    private function isCacheableKeyword(AbstractNode $node): bool
    {
        return $node instanceof LiteralNode && $node->getValue() instanceof Keyword;
    }

    private function isEmpty(AbstractNode $node): bool
    {
        return match (true) {
            $node instanceof VectorNode => $node->getArgs() === [],
            $node instanceof MapNode => $node->getKeyValues() === [],
            $node instanceof SetNode => $node->getValues() === [],
            default => false,
        };
    }

    /**
     * Manual node-type registry. Any AST node added in the future that can
     * contain a child collection literal must be listed here, otherwise the
     * scanner silently produces no children for it and literals nested in
     * the new node will never be hoisted (safe miss, not wrong code).
     * Auditing the {@see \Phel\Compiler\Domain\Analyzer\Ast} namespace when
     * introducing a new node type keeps this list current.
     *
     * @return list<AbstractNode>
     */
    private function children(AbstractNode $node): array
    {
        return match (true) {
            $node instanceof CallNode => [$node->getFn(), ...$node->getArguments()],
            $node instanceof ApplyNode => [$node->getFn(), ...$node->getArguments()],
            $node instanceof IfNode => [$node->getTestExpr(), $node->getThenExpr(), $node->getElseExpr()],
            $node instanceof DoNode => [...$node->getStmts(), $node->getRet()],
            $node instanceof LetNode => $this->letChildren($node),
            $node instanceof RecurNode => $node->getExpressions(),
            $node instanceof TryNode => $this->tryChildren($node),
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
            default => [],
        };
    }

    /**
     * @return list<AbstractNode>
     */
    private function letChildren(LetNode $node): array
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
    private function tryChildren(TryNode $node): array
    {
        $children = [$node->getBody(), ...$node->getCatches()];
        $finally = $node->getFinally();
        if ($finally instanceof AbstractNode) {
            $children[] = $finally;
        }

        return $children;
    }
}
