<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\StringNode;
use Phel\Shared\Parser\Node\Token;

use function array_slice;
use function count;
use function in_array;

/**
 * Deletes one form from a body that has more than one: a definition body,
 * one arity of a multi-arity definition, or a `do` block. Dropping the last
 * form changes what comes back, dropping an earlier one silently removes a
 * side effect; a suite that never observes either keeps passing.
 *
 * A body with a single form is left alone — removing it would test whether
 * the function is called at all, not what it does.
 *
 * @internal
 */
final class BodyDropMutator implements MutatorInterface
{
    private const array DEFINITION_HEADS = ['defn', 'defn-'];

    private const string DO_HEAD = 'do';

    public function id(): string
    {
        return 'body-drop';
    }

    public function mutate(InnerNodeInterface $parent, int $index, NodeInterface $child): array
    {
        $bodyIndices = $this->bodyIndices($parent);
        if (count($bodyIndices) < 2 || !in_array($index, $bodyIndices, true)) {
            return [];
        }

        $children = Nodes::withoutChild($parent->getChildren(), $index);

        // The parent is a whole body; naming the dropped form is what reads
        // well in a report, not the body before and after.
        return [new Replacement($children, $child->getCode() . ' -> (removed)')];
    }

    /**
     * The child indices of `$parent` that are body forms, in source order,
     * or an empty list when `$parent` is not a form that has a body.
     *
     * @return list<int>
     */
    private function bodyIndices(InnerNodeInterface $parent): array
    {
        if (!$parent instanceof ListNode || $parent->getTokenType() !== Token::T_OPEN_PARENTHESIS) {
            return [];
        }

        $children = $parent->getChildren();
        $significant = Nodes::significantIndices($children);
        if ($significant === []) {
            return [];
        }

        $head = $children[$significant[0]];

        // One arity of a multi-arity definition: `([params] body…)`.
        if (Nodes::isVector($head)) {
            return array_slice($significant, 1);
        }

        $name = Nodes::symbolName($head);
        if ($name === self::DO_HEAD) {
            return array_slice($significant, 1);
        }

        if (in_array($name, self::DEFINITION_HEADS, true)) {
            return $this->definitionBodyIndices($children, $significant);
        }

        return [];
    }

    /**
     * The body of `(defn name docstring? attr-map? [params] body…)`: what
     * follows the parameter vector. A definition without one is multi-arity,
     * and its bodies belong to the arity lists rather than to this list.
     *
     * @param list<NodeInterface> $children
     * @param list<int>           $significant indices of `$children` that are not trivia
     *
     * @return list<int>
     */
    private function definitionBodyIndices(array $children, array $significant): array
    {
        $position = 1; // the name
        while (isset($significant[$position + 1]) && $this->isPrelude($children[$significant[$position + 1]])) {
            ++$position;
        }

        ++$position; // the parameter vector
        if (!isset($significant[$position]) || !Nodes::isVector($children[$significant[$position]])) {
            return [];
        }

        return array_slice($significant, $position + 1);
    }

    /**
     * A docstring or an attribute map: written between the name and the
     * parameters, never part of the body.
     */
    private function isPrelude(NodeInterface $node): bool
    {
        return $node instanceof StringNode
            || ($node instanceof ListNode && $node->getTokenType() === Token::T_OPEN_BRACE);
    }
}
