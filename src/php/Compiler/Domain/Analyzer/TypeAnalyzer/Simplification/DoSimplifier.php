<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;

/**
 * Drops `LiteralNode` expressions from a `(do ...)` body's non-tail
 * positions after analysis. The tail expression is always preserved
 * because it is the form's return value.
 *
 * Scope is intentionally narrow — only literal nodes are stripped.
 * Any other "pure" candidate (`LocalVarNode` references, foldable
 * core calls, …) is kept, because the analyser is allowed to lower
 * those into emitter shapes whose evaluation order or side effects
 * the simplifier cannot statically prove harmless (e.g. registering a
 * test assertion counter on a folded predicate).
 */
final readonly class DoSimplifier
{
    public function simplify(DoNode $node): DoNode
    {
        $stmts = $node->getStmts();
        $filtered = [];
        $dropped = false;
        foreach ($stmts as $stmt) {
            if ($stmt instanceof LiteralNode) {
                $dropped = true;
                continue;
            }

            $filtered[] = $stmt;
        }

        if (!$dropped) {
            return $node;
        }

        return new DoNode(
            $node->getEnv(),
            $filtered,
            $node->getRet(),
            $node->getStartSourceLocation(),
        );
    }
}
