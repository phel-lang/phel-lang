<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpRefNode;
use Phel\Compiler\Domain\Analyzer\Ast\ReifyNode;

use function array_unique;
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
 * help. Children come from the shared {@see NodeChildren} map; an unrecognised
 * node yields `[]` here (by-value fallback). That fallback is safe for a genuine
 * leaf, but a missed *container* silently drops a `php/ref` write — which is why
 * the child map is shared with {@see YieldDetector} rather than hand-copied
 * (the copy had already lost `SetNode`).
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

        foreach (NodeChildren::of($node) ?? [] as $child) {
            $this->walk($child, $names);
        }
    }
}
