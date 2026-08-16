<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\NodeInterface;

/**
 * Inverts an equality test: `=` becomes `not=` and `not=` becomes `=`. A
 * test suite that only ever asserts the branch taken for one of the two
 * outcomes cannot tell the difference.
 *
 * @internal
 */
final class EqualityMutator implements MutatorInterface
{
    use SymbolSwapTrait;

    private const array PAIRS = [
        '=' => 'not=',
        'not=' => '=',
    ];

    public function id(): string
    {
        return 'equality';
    }

    public function mutate(InnerNodeInterface $parent, int $index, NodeInterface $child): array
    {
        return $this->swapSymbol($parent, $index, $child, self::PAIRS);
    }
}
