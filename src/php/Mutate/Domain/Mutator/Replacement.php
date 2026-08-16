<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\NodeInterface;

/**
 * One alternative child list for a parent node, plus the short human
 * description of what changed (`(< a b) -> (<= a b)`), which is what the
 * report prints next to a surviving mutant.
 *
 * @internal
 */
final readonly class Replacement
{
    /**
     * @param list<NodeInterface> $children the parent's complete new child list
     */
    public function __construct(
        public array $children,
        public string $description,
    ) {}
}
