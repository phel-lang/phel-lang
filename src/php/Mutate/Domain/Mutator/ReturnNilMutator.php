<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\NilNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\Token;

use function count;
use function in_array;

/**
 * Replaces the last form of a definition body — the value it returns — with
 * `nil`, keeping everything the body did before it. A test that calls the
 * function for its side effects only, or that asserts nothing about the
 * value it hands back, keeps passing.
 *
 * @internal
 */
final class ReturnNilMutator implements MutatorInterface
{
    private const array DEFINITION_HEADS = ['defn', 'defn-'];

    public function id(): string
    {
        return 'return-nil';
    }

    public function mutate(InnerNodeInterface $parent, int $index, NodeInterface $child): array
    {
        if ($child instanceof NilNode || !$this->isDefinitionBody($parent)) {
            return [];
        }

        $children = $parent->getChildren();
        if ($index !== $this->lastSignificantIndex($children)) {
            return [];
        }

        $mutated = Nodes::withChildReplaced($children, $index, Nodes::nil($child));

        // The parent is a whole definition; naming the returned form is what
        // reads well in a report, not the definition before and after.
        return [new Replacement($mutated, $child->getCode() . ' -> nil')];
    }

    /**
     * Whether `$parent` is a list whose tail is a definition body: a
     * single-arity `(defn name … [params] body…)`, or one arity of a
     * multi-arity definition, which reads `([params] body…)`.
     */
    private function isDefinitionBody(InnerNodeInterface $parent): bool
    {
        if (!$parent instanceof ListNode || $parent->getTokenType() !== Token::T_OPEN_PARENTHESIS) {
            return false;
        }

        $children = $parent->getChildren();
        $head = $children[Nodes::firstSignificantIndex($children)] ?? null;
        if (!$head instanceof NodeInterface) {
            return false;
        }

        return in_array(Nodes::symbolName($head), self::DEFINITION_HEADS, true)
            || Nodes::isVector($head);
    }

    /**
     * Index of the last non-trivia child, or -1 when the list holds none;
     * trailing whitespace and comments must not hide the returned form.
     *
     * @param list<NodeInterface> $children
     */
    private function lastSignificantIndex(array $children): int
    {
        $indices = Nodes::significantIndices($children);

        return $indices === [] ? -1 : $indices[count($indices) - 1];
    }
}
