<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\NodeInterface;

/**
 * One kind of small, plausible programmer mistake. Given a node and the
 * list it sits in, a mutator answers with the alternative child lists that
 * introduce that mistake at that position; each alternative is one mutant.
 *
 * Mutators work on the parse tree (CST), never on source text: the tree
 * round-trips through `getCode()` byte for byte, so the only textual
 * difference between the original and a mutant is the mutation itself.
 * They must be pure: build new node lists, never call `replaceChildren()`.
 *
 * @internal
 */
interface MutatorInterface
{
    /**
     * Short stable identifier used in reports and in `--only`, e.g. `arith`.
     */
    public function id(): string;

    /**
     * Alternatives for `$parent`'s children with the child at `$index`
     * mutated (or removed). An empty list means this mutator has nothing to
     * say about that node.
     *
     * @return list<Replacement>
     */
    public function mutate(InnerNodeInterface $parent, int $index, NodeInterface $child): array;
}
