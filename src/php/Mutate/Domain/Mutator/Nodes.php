<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Lang\Symbol;
use Phel\Shared\Parser\Node\BooleanNode;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\NilNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\NumberNode;
use Phel\Shared\Parser\Node\StringNode;
use Phel\Shared\Parser\Node\SymbolNode;
use Phel\Shared\Parser\Node\Token;
use Phel\Shared\Parser\Node\TriviaNodeInterface;

use function array_slice;
use function array_values;
use function is_int;

/**
 * Builds the replacement atoms a mutator drops into a child list, and the
 * small child-list edits every mutator needs. Every new atom borrows the
 * source location of the node it replaces so an error inside a mutant still
 * points at the right line.
 *
 * @internal
 */
final class Nodes
{
    public static function symbol(string $name, NodeInterface $like): SymbolNode
    {
        return new SymbolNode($name, $like->getStartLocation(), $like->getEndLocation(), Symbol::create($name));
    }

    public static function number(int|float $value, NodeInterface $like): NumberNode
    {
        return new NumberNode((string) $value, $like->getStartLocation(), $like->getEndLocation(), $value);
    }

    public static function boolean(bool $value, NodeInterface $like): BooleanNode
    {
        return new BooleanNode($value ? 'true' : 'false', $like->getStartLocation(), $like->getEndLocation(), $value);
    }

    public static function string(string $value, NodeInterface $like): StringNode
    {
        return new StringNode('"' . $value . '"', $like->getStartLocation(), $like->getEndLocation(), $value);
    }

    public static function nil(NodeInterface $like): NilNode
    {
        return new NilNode('nil', $like->getStartLocation(), $like->getEndLocation(), null);
    }

    /**
     * `$children` with the element at `$index` swapped for `$node`.
     *
     * @param list<NodeInterface> $children
     *
     * @return list<NodeInterface>
     */
    public static function withChildReplaced(array $children, int $index, NodeInterface $node): array
    {
        $children[$index] = $node;

        return array_values($children);
    }

    /**
     * `$children` without the element at `$index` and without the trivia
     * (whitespace, newlines, comments) that immediately preceded it, so the
     * removal reads as one deleted form rather than a form plus a gap.
     *
     * @param list<NodeInterface> $children
     *
     * @return list<NodeInterface>
     */
    public static function withoutChild(array $children, int $index): array
    {
        $from = $index;
        while ($from > 0 && $children[$from - 1] instanceof TriviaNodeInterface) {
            --$from;
        }

        return [...array_slice($children, 0, $from), ...array_slice($children, $index + 1)];
    }

    /**
     * Indexes of the non-trivia children, in order.
     *
     * @param list<NodeInterface> $children
     *
     * @return list<int>
     */
    public static function significantIndices(array $children): array
    {
        $indices = [];
        foreach ($children as $index => $child) {
            if (!$child instanceof TriviaNodeInterface) {
                $indices[] = $index;
            }
        }

        return $indices;
    }

    /**
     * Index of the first non-trivia child at or after `$from`; one past the
     * end when there is none.
     *
     * @param list<NodeInterface> $children
     */
    public static function firstSignificantIndex(array $children, int $from = 0): int
    {
        $index = $from;
        while (isset($children[$index]) && $children[$index] instanceof TriviaNodeInterface) {
            ++$index;
        }

        return $index;
    }

    /**
     * A `[...]` vector node, the shape of a parameter list.
     */
    public static function isVector(NodeInterface $node): bool
    {
        return $node instanceof ListNode && $node->getTokenType() === Token::T_OPEN_BRACKET;
    }

    /**
     * The full name of a symbol node (`s/join`, `+`), or null for any other node.
     */
    public static function symbolName(NodeInterface $node): ?string
    {
        if (!$node instanceof SymbolNode) {
            return null;
        }

        return $node->getValue()->getFullName();
    }

    public static function isInt(NodeInterface $node): bool
    {
        return $node instanceof NumberNode && is_int($node->getValue());
    }

    public static function isNonEmptyString(NodeInterface $node): bool
    {
        return $node instanceof StringNode && $node->getValue() !== '';
    }
}
