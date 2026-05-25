<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\Ast\DoNode;

/**
 * Drops pure expressions from a `(do ...)` body's non-tail positions
 * after analysis. The tail expression is always preserved because it
 * is the form's return value.
 *
 * Side effects in non-tail positions stay; only computations with no
 * observable effect are removed, so behaviour is unchanged.
 */
final readonly class DoSimplifier
{
    public function __construct(
        private PureExpressionDetector $purity = new PureExpressionDetector(),
    ) {}

    public function simplify(DoNode $node): DoNode
    {
        $stmts = $node->getStmts();
        $filtered = [];
        $dropped = false;
        foreach ($stmts as $stmt) {
            if ($this->purity->isPure($stmt)) {
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
