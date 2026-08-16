<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\NodeInterface;

/**
 * Swaps an arithmetic operator for its counterpart: `+` and `-`, `*` and `/`.
 *
 * @internal
 */
final class ArithmeticMutator implements MutatorInterface
{
    use SymbolSwapTrait;

    private const array PAIRS = [
        '+' => '-',
        '-' => '+',
        '*' => '/',
        '/' => '*',
    ];

    public function id(): string
    {
        return 'arith';
    }

    public function mutate(InnerNodeInterface $parent, int $index, NodeInterface $child): array
    {
        return $this->swapSymbol($parent, $index, $child, self::PAIRS);
    }
}
