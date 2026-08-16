<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\Token;
use Phel\Shared\Parser\Node\TriviaNodeInterface;
use Phel\Shared\Parser\Node\WhitespaceNode;

use function in_array;

/**
 * Negates the test of a conditional — `if`, `if-not`, `when`, `when-not` —
 * by wrapping it in `(not …)`, so every branch is taken exactly when it
 * used to be skipped. A suite that only ever exercises one side of a
 * conditional keeps passing.
 *
 * @internal
 */
final class ConditionMutator implements MutatorInterface
{
    private const array HEADS = ['if', 'if-not', 'when', 'when-not'];

    private const string NOT = 'not';

    public function id(): string
    {
        return 'cond-branch';
    }

    public function mutate(InnerNodeInterface $parent, int $index, NodeInterface $child): array
    {
        if (!$child instanceof ListNode || $child->getTokenType() !== Token::T_OPEN_PARENTHESIS) {
            return [];
        }

        $children = $child->getChildren();
        $headIndex = $this->significantIndex($children, 0);
        if (!isset($children[$headIndex]) || !in_array(Nodes::symbolName($children[$headIndex]), self::HEADS, true)) {
            return [];
        }

        $testIndex = $this->significantIndex($children, $headIndex + 1);
        if (!isset($children[$testIndex])) {
            return [];
        }

        $test = $children[$testIndex];
        if ($this->isNegation($test)) {
            return [];
        }

        $mutatedChild = new ListNode(
            $child->getTokenType(),
            $child->getStartLocation(),
            $child->getEndLocation(),
            Nodes::withChildReplaced($children, $testIndex, $this->negate($test)),
        );

        return [new Replacement(
            Nodes::withChildReplaced($parent->getChildren(), $index, $mutatedChild),
            $child->getCode() . ' -> ' . $mutatedChild->getCode(),
        )];
    }

    /**
     * `$test` wrapped in a freshly built `(not $test)` list. Both the wrapper
     * and its separating space borrow the operand's span, so a failure inside
     * a mutant still points at the original line and column.
     */
    private function negate(NodeInterface $test): ListNode
    {
        return new ListNode(
            Token::T_OPEN_PARENTHESIS,
            $test->getStartLocation(),
            $test->getEndLocation(),
            [
                Nodes::symbol(self::NOT, $test),
                new WhitespaceNode(' ', $test->getStartLocation(), $test->getEndLocation()),
                $test,
            ],
        );
    }

    /**
     * Whether the test already reads `(not …)`; wrapping it again would
     * cancel out and produce a mutant identical to the original.
     */
    private function isNegation(NodeInterface $test): bool
    {
        if (!$test instanceof ListNode || $test->getTokenType() !== Token::T_OPEN_PARENTHESIS) {
            return false;
        }

        $children = $test->getChildren();
        $headIndex = $this->significantIndex($children, 0);

        return isset($children[$headIndex]) && Nodes::symbolName($children[$headIndex]) === self::NOT;
    }

    /**
     * Index of the first non-trivia child at or after `$from`; one past the
     * end when there is none.
     *
     * @param list<NodeInterface> $children
     */
    private function significantIndex(array $children, int $from): int
    {
        $index = $from;
        while (isset($children[$index]) && $children[$index] instanceof TriviaNodeInterface) {
            ++$index;
        }

        return $index;
    }
}
