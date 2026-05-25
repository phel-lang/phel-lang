<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\LocalVarReferences;

use function array_reverse;
use function array_slice;
use function count;
use function in_array;

/**
 * Two-fold rewrite of `let` after analysis:
 *
 *  1. **Drop** bindings whose shadow name is unreferenced from any
 *     later init and from the body **and** whose init is pure.
 *  2. **Inline** the final binding when the body is `DoNode([], <local>)`
 *     and the local is the final binding's shadow — the whole `let`
 *     collapses to the init expression in the let's env.
 *
 * `loop` bindings are left untouched (`recur` rebinds them).
 *
 * Subtrees that emit a PHP closure (`FnNode`, `MultiFnNode`, `ForeachNode`,
 * `TryNode`) keep the let intact: those emitters capture every in-scope
 * local via a fixed `use(...)` clause; dropping or inlining a binding
 * would leave the capture pointing at a missing variable.
 */
final readonly class LetSimplifier
{
    public function __construct(
        private PureExpressionDetector $purity = new PureExpressionDetector(),
    ) {}

    public function simplify(LetNode $node): AbstractNode
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

        $simplified = $this->dropUnusedBindings($node);

        return $this->inlineSingleTailUse($simplified);
    }

    private function dropUnusedBindings(LetNode $node): LetNode
    {
        $bindings = $node->getBindings();
        $body = $node->getBodyExpr();

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

    /**
     * Collapse `(let [… x init] x)` to `init`. The body's tail must be
     * a `LocalVarNode` whose shadow matches the last binding, and there
     * must be no non-tail body statements (those could observe `x`'s
     * binding via implicit captures).
     *
     * Counting references is sufficient here: if the body's tail is the
     * last binding's shadow **and** the only reference in the body is
     * that tail, the binding has exactly one use site and substituting
     * it preserves evaluation order. Earlier bindings (whose inits may
     * reference the last binding's shadow) would have been kept by the
     * drop pass and would still be visible in the reference set; we
     * verify by checking the shadow appears exactly once.
     */
    private function inlineSingleTailUse(LetNode $node): AbstractNode
    {
        $bindings = $node->getBindings();
        if ($bindings === []) {
            return $node;
        }

        $body = $node->getBodyExpr();
        if (!$body instanceof DoNode || $body->getStmts() !== []) {
            return $node;
        }

        $tail = $body->getRet();
        if (!$tail instanceof LocalVarNode) {
            return $node;
        }

        $lastBinding = $bindings[count($bindings) - 1];
        if ($lastBinding->getShadow()->getName() !== $tail->getName()->getName()) {
            return $node;
        }

        if (!$this->purity->isPure($lastBinding->getInitExpr())) {
            return $node;
        }

        $shadow = $lastBinding->getShadow()->getName();
        $refsInBody = LocalVarReferences::collect($body);
        if ($refsInBody === null) {
            return $node;
        }

        // The shadow appears at the tail; ensure nothing else references it.
        $occurrences = 0;
        foreach ($refsInBody as $ref) {
            if ($ref === $shadow) {
                ++$occurrences;
            }
        }

        if ($occurrences !== 1) {
            return $node;
        }

        $remaining = array_slice($bindings, 0, -1);
        $init = $lastBinding->getInitExpr();

        // Only inline a `LiteralNode` init. Re-emitting any other node
        // type would need to rebase its env onto the let's outer
        // context (statement vs expression vs return), and the
        // analyser doesn't yet expose a safe way to do that.
        if (!$init instanceof LiteralNode) {
            return $node;
        }

        $rebased = new LiteralNode($node->getEnv(), $init->getValue(), $node->getStartSourceLocation());

        if ($remaining === []) {
            return $rebased;
        }

        return new LetNode(
            $node->getEnv(),
            $remaining,
            new DoNode($body->getEnv(), [], $rebased, $body->getStartSourceLocation()),
            $node->isLoop(),
            $node->getStartSourceLocation(),
        );
    }
}
