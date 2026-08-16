<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\NumberNode;

use function is_int;

/**
 * Shifts an integer literal to its neighbour: `1` becomes `0`, and every
 * other `n` becomes `n + 1`. This is the classic off-by-one — a wrong index,
 * a wrong count, a wrong limit — which survives any assertion that only
 * checks the shape of a result instead of its value.
 *
 * @internal
 */
final class NumberLiteralMutator implements MutatorInterface
{
    public function id(): string
    {
        return 'literal-num';
    }

    public function mutate(InnerNodeInterface $parent, int $index, NodeInterface $child): array
    {
        $value = $this->intValueOf($child);
        if ($value === null) {
            return [];
        }

        $shifted = Nodes::number($value === 1 ? 0 : $value + 1, $child);
        $children = Nodes::withChildReplaced($parent->getChildren(), $index, $shifted);

        return [new Replacement($children, Description::ofChildren($parent, $children))];
    }

    /**
     * The value of a plain PHP integer literal, null for anything else.
     * Floats, ratios and big numbers keep their spelling: they have no
     * obvious neighbour, and their spelling would not survive the round trip
     * through `(string)`. This is `Nodes::isInt`, read for the value
     * rather than asked as a predicate.
     */
    private function intValueOf(NodeInterface $node): ?int
    {
        if (!$node instanceof NumberNode) {
            return null;
        }

        $value = $node->getValue();

        return is_int($value) ? $value : null;
    }
}
