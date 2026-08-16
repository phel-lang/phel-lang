<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\Token;
use Phel\Shared\Parser\Node\TriviaNodeInterface;

use function count;

/**
 * Weakens boolean logic two ways: `and` and `or` trade places, and a
 * `(not X)` wrapper is dropped so the guarded form is used unnegated. Both
 * are the shape of a condition that was written the wrong way round.
 *
 * @internal
 */
final class LogicMutator implements MutatorInterface
{
    use SymbolSwapTrait;

    private const array PAIRS = [
        'and' => 'or',
        'or' => 'and',
    ];

    private const string NOT = 'not';

    public function id(): string
    {
        return 'logic';
    }

    public function mutate(InnerNodeInterface $parent, int $index, NodeInterface $child): array
    {
        $swapped = $this->swapSymbol($parent, $index, $child, self::PAIRS);
        if ($swapped !== []) {
            return $swapped;
        }

        return $this->unwrapNot($parent, $index, $child);
    }

    /**
     * `(not X)` standing at `$index` collapses to `X`: the negation goes and
     * the form it guarded stays where it was.
     *
     * @return list<Replacement>
     */
    private function unwrapNot(InnerNodeInterface $parent, int $index, NodeInterface $child): array
    {
        $inner = $this->negatedForm($child);
        if (!$inner instanceof NodeInterface) {
            return [];
        }

        $children = Nodes::withChildReplaced($parent->getChildren(), $index, $inner);

        return [new Replacement($children, $child->getCode() . ' -> ' . $inner->getCode())];
    }

    /**
     * The single operand of a `(not X)` call, or null for anything else —
     * including a `not` applied to no or several arguments, which is not a
     * negation this mutator knows how to undo.
     */
    private function negatedForm(NodeInterface $node): ?NodeInterface
    {
        if (!$node instanceof ListNode || $node->getTokenType() !== Token::T_OPEN_PARENTHESIS) {
            return null;
        }

        $significant = [];
        foreach ($node->getChildren() as $child) {
            if (!$child instanceof TriviaNodeInterface) {
                $significant[] = $child;
            }
        }

        if (count($significant) !== 2 || Nodes::symbolName($significant[0]) !== self::NOT) {
            return null;
        }

        return $significant[1];
    }
}
