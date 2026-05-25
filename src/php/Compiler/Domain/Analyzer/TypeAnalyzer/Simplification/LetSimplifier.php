<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\LocalVarReferences;

use function array_reverse;
use function in_array;

/**
 * Drops `let` bindings whose shadow name is never referenced from any
 * later binding's init nor from the body, **and** whose init is pure.
 *
 * Pure-init-only is the cautious half of the spec — keeping an impure
 * init alive as a body statement is left to a follow-up that can
 * verify the prepended init does not introduce env / context drift on
 * the body's `DoNode`.
 *
 * `loop` bindings are left untouched: `recur` reassigns the binding
 * values at runtime, so even an unused-by-the-body-now binding may be
 * reassigned and observed.
 *
 * The pass also leaves the let alone when the body's reference set
 * cannot be statically determined — for now this includes any subtree
 * holding an `FnNode` / `MultiFnNode`, whose `use(...)` clauses are
 * fixed at analysis time and would dangle on a dropped capture.
 */
final readonly class LetSimplifier
{
    public function __construct(
        private PureExpressionDetector $purity = new PureExpressionDetector(),
    ) {}

    public function simplify(LetNode $node): LetNode
    {
        if ($node->isLoop()) {
            return $node;
        }

        $bindings = $node->getBindings();
        if ($bindings === []) {
            return $node;
        }

        $body = $node->getBodyExpr();
        if (LocalVarFnGuard::containsClosure($body)) {
            return $node;
        }

        foreach ($bindings as $binding) {
            if (LocalVarFnGuard::containsClosure($binding->getInitExpr())) {
                return $node;
            }
        }

        $bodyRefs = LocalVarReferences::collect($body);
        if ($bodyRefs === null) {
            return $node;
        }

        /** @var list<string> $allRefs */
        $allRefs = $bodyRefs;
        $keepReversed = [];
        $dropped = false;

        foreach (array_reverse($bindings) as $binding) {
            $shadow = $binding->getShadow()->getName();
            $init = $binding->getInitExpr();

            $stillReferenced = in_array($shadow, $allRefs, true);
            $initPure = $this->purity->isPure($init);

            if ($stillReferenced || !$initPure) {
                $keepReversed[] = $binding;
                $extra = LocalVarReferences::collect($init);
                if ($extra === null) {
                    return $node;
                }

                $allRefs = [...$allRefs, ...$extra];

                continue;
            }

            $dropped = true;
        }

        if (!$dropped) {
            return $node;
        }

        return new LetNode(
            $node->getEnv(),
            array_reverse($keepReversed),
            $body,
            $node->isLoop(),
            $node->getStartSourceLocation(),
        );
    }
}
