<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\NodeInterface;

/**
 * The shape most mutators share: a symbol at the site is replaced by
 * another symbol from a fixed table, and the description shows the parent
 * form before and after.
 *
 * @internal
 */
trait SymbolSwapTrait
{
    /**
     * @param array<string, string> $swaps symbol name => replacement name
     *
     * @return list<Replacement>
     */
    private function swapSymbol(InnerNodeInterface $parent, int $index, NodeInterface $child, array $swaps): array
    {
        $name = Nodes::symbolName($child);
        if ($name === null || !isset($swaps[$name])) {
            return [];
        }

        return [$this->replaceChild($parent, $index, Nodes::symbol($swaps[$name], $child))];
    }

    /**
     * A replacement swapping the child at `$index` for `$node`, described as
     * the parent's code before and after.
     */
    private function replaceChild(InnerNodeInterface $parent, int $index, NodeInterface $node): Replacement
    {
        $children = Nodes::withChildReplaced($parent->getChildren(), $index, $node);

        return new Replacement($children, Description::ofChildren($parent, $children));
    }
}
