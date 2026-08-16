<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\BooleanNode;
use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\NodeInterface;

/**
 * Flips a literal `true` into `false` and back. Default flags, sentinel
 * return values and the constant arm of a conditional are all covered by
 * this one substitution.
 *
 * @internal
 */
final class BooleanLiteralMutator implements MutatorInterface
{
    public function id(): string
    {
        return 'literal-bool';
    }

    public function mutate(InnerNodeInterface $parent, int $index, NodeInterface $child): array
    {
        if (!$child instanceof BooleanNode) {
            return [];
        }

        $flipped = Nodes::boolean(!$child->getValue(), $child);
        $children = Nodes::withChildReplaced($parent->getChildren(), $index, $flipped);

        return [new Replacement($children, Description::ofChildren($parent, $children))];
    }
}
