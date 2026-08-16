<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\NodeInterface;

/**
 * Moves a comparison across its own boundary: `<` becomes `<=`, `>` becomes
 * `>=`, and back. The mistake it imitates is the classic off-by-one at the
 * edge of a range, which survives every test that never feeds it the
 * boundary value itself.
 *
 * @internal
 */
final class ComparisonMutator implements MutatorInterface
{
    use SymbolSwapTrait;

    private const array PAIRS = [
        '<' => '<=',
        '<=' => '<',
        '>' => '>=',
        '>=' => '>',
    ];

    public function id(): string
    {
        return 'compare';
    }

    public function mutate(InnerNodeInterface $parent, int $index, NodeInterface $child): array
    {
        return $this->swapSymbol($parent, $index, $child, self::PAIRS);
    }
}
