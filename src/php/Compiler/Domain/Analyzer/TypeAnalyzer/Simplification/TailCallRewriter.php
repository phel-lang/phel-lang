<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;

use function count;

/**
 * Rewrites a self-recursive `defn` body so that calls to the surrounding
 * function in tail position become implicit `recur` jumps. Pairs with
 * `FnAsClassEmitter` / `MethodEmitter`, which wrap a fn whose
 * `RecurFrame` is active in a `while (true) { ... break; }` loop.
 *
 * Out of scope (kept as ordinary calls):
 *
 *  - variadic fns (rest-arg destructuring would change semantics)
 *  - multi-arity overloads (cross-arity recur is undefined)
 *  - calls inside `try` / `catch` / `finally` bodies (control-flow
 *    invariants tightened in a follow-up)
 *
 * The rewriter is read-only with respect to user observability except
 * for stack-trace shape: a recursive descent of N frames becomes a
 * single frame with a loop. That trade-off is gated behind
 * `CompileOptions::setOptimizationLevel(2)`.
 */
final readonly class TailCallRewriter
{
    /**
     * @return array{0: AbstractNode, 1: bool} the rewritten body and a
     *                                         flag indicating whether
     *                                         any tail-call was actually
     *                                         turned into a `recur`
     */
    public function rewrite(
        AbstractNode $body,
        RecurFrame $recurFrame,
        string $selfNs,
        string $selfName,
        int $paramCount,
        bool $isVariadic,
    ): array {
        if ($isVariadic) {
            return [$body, false];
        }

        $rewrote = false;
        $rewritten = $this->walk($body, true, $recurFrame, $selfNs, $selfName, $paramCount, $rewrote);

        return [$rewritten, $rewrote];
    }

    private function walk(
        AbstractNode $node,
        bool $tail,
        RecurFrame $recurFrame,
        string $selfNs,
        string $selfName,
        int $paramCount,
        bool &$rewrote,
    ): AbstractNode {
        if ($node instanceof DoNode) {
            return new DoNode(
                $node->getEnv(),
                $node->getStmts(),
                $this->walk($node->getRet(), $tail, $recurFrame, $selfNs, $selfName, $paramCount, $rewrote),
                $node->getStartSourceLocation(),
            );
        }

        if ($node instanceof IfNode) {
            return new IfNode(
                $node->getEnv(),
                $node->getTestExpr(),
                $this->walk($node->getThenExpr(), $tail, $recurFrame, $selfNs, $selfName, $paramCount, $rewrote),
                $this->walk($node->getElseExpr(), $tail, $recurFrame, $selfNs, $selfName, $paramCount, $rewrote),
                $node->getStartSourceLocation(),
            );
        }

        if ($node instanceof LetNode) {
            // Skipping `loop` (let with isLoop=true): it owns its own
            // RecurFrame, so any inner self-call would target the loop's
            // frame rather than the enclosing fn's.
            if ($node->isLoop()) {
                return $node;
            }

            return new LetNode(
                $node->getEnv(),
                $node->getBindings(),
                $this->walk($node->getBodyExpr(), $tail, $recurFrame, $selfNs, $selfName, $paramCount, $rewrote),
                $node->isLoop(),
                $node->getStartSourceLocation(),
            );
        }

        if ($tail && $node instanceof CallNode && $this->isSelfCall($node, $selfNs, $selfName, $paramCount)) {
            $recurFrame->setIsActive(true);
            $rewrote = true;

            return new RecurNode(
                $node->getEnv(),
                $recurFrame,
                $node->getArguments(),
                $node->getStartSourceLocation(),
            );
        }

        return $node;
    }

    private function isSelfCall(CallNode $node, string $selfNs, string $selfName, int $paramCount): bool
    {
        if (count($node->getArguments()) !== $paramCount) {
            return false;
        }

        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return false;
        }

        return $fn->getNamespace() === $selfNs
            && $fn->getName()->getName() === $selfName;
    }
}
