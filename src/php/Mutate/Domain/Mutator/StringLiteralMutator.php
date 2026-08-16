<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\NodeInterface;

/**
 * Empties a string literal. Separators, prefixes, message text and format
 * strings all vanish, so any test that asserts on the exact text produced by
 * a definition kills the mutant, and one that only checks "a string came
 * back" does not.
 *
 * An already empty string is left alone: the mutation would be a no-op.
 *
 * @internal
 */
final class StringLiteralMutator implements MutatorInterface
{
    public function id(): string
    {
        return 'literal-str';
    }

    public function mutate(InnerNodeInterface $parent, int $index, NodeInterface $child): array
    {
        if (!Nodes::isNonEmptyString($child)) {
            return [];
        }

        // Only ever the empty string: `Nodes::string()` quotes without
        // escaping, so any other value could break the round trip.
        $emptied = Nodes::string('', $child);
        $children = Nodes::withChildReplaced($parent->getChildren(), $index, $emptied);

        return [new Replacement($children, Description::ofChildren($parent, $children))];
    }
}
