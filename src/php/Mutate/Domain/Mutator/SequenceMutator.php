<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\NodeInterface;

/**
 * Swaps a sequence operation for the one that goes the other way: `first`
 * and `last`, `inc` and `dec`, `conj` and `disj`, `take` and `drop`, `min`
 * and `max`. Each pair keeps the call's arity and the type of its result, so
 * only an assertion on the actual value kills the mutant.
 *
 * @internal
 */
final class SequenceMutator implements MutatorInterface
{
    use SymbolSwapTrait;

    private const array PAIRS = [
        'first' => 'last',
        'last' => 'first',
        'inc' => 'dec',
        'dec' => 'inc',
        'conj' => 'disj',
        'disj' => 'conj',
        'take' => 'drop',
        'drop' => 'take',
        'min' => 'max',
        'max' => 'min',
    ];

    public function id(): string
    {
        return 'seq-op';
    }

    public function mutate(InnerNodeInterface $parent, int $index, NodeInterface $child): array
    {
        return $this->swapSymbol($parent, $index, $child, self::PAIRS);
    }
}
