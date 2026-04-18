<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules;

use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\ParserNode\CommentNode;
use Phel\Compiler\Domain\Parser\ParserNode\InnerNodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\ListNode;
use Phel\Compiler\Domain\Parser\ParserNode\NewlineNode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\SymbolNode;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\WhitespaceNode;
use Phel\Lang\SourceLocation;

use function array_slice;
use function count;
use function in_array;
use function strlen;

final readonly class AlignPairsRule implements RuleInterface
{
    /**
     * Forms whose tail (after N leading non-trivia nodes) is a sequence of
     * key/value pairs to align. Value: number of non-trivia nodes to skip
     * before pairs start (0 = head only, 1 = head + expr, etc.).
     */
    private const array PAIR_FORMS = [
        'cond' => 1,
        'case' => 2,
        'condp' => 3,
    ];

    /** Forms whose first value-child is a vector of key/value binding pairs. */
    private const array BINDING_FORMS = [
        'let',
        'loop',
        'binding',
        'for',
        'foreach',
        'dofor',
        'if-let',
        'when-let',
    ];

    public function transform(NodeInterface $node): NodeInterface
    {
        return $this->visit($node);
    }

    private function visit(NodeInterface $node): NodeInterface
    {
        if (!$node instanceof InnerNodeInterface) {
            return $node;
        }

        $children = [];
        foreach ($node->getChildren() as $child) {
            $children[] = $this->visit($child);
        }

        $node->replaceChildren($children);

        if ($node instanceof ListNode && $node->getTokenType() === Token::T_OPEN_PARENTHESIS) {
            $this->applyFormAlignment($node);
        }

        return $node;
    }

    private function applyFormAlignment(ListNode $node): void
    {
        $children = $node->getChildren();
        $headName = $this->headName($children);
        if ($headName === null) {
            return;
        }

        if (isset(self::PAIR_FORMS[$headName])) {
            $node->replaceChildren(
                $this->alignPairs($children, self::PAIR_FORMS[$headName], allowTrailingSingle: $headName !== 'cond'),
            );
            return;
        }

        if (in_array($headName, self::BINDING_FORMS, true)) {
            $vector = $this->firstVectorChild($children);
            if ($vector instanceof ListNode) {
                $vector->replaceChildren($this->alignPairs($vector->getChildren(), 0));
            }
        }
    }

    /**
     * @param list<NodeInterface> $children
     */
    private function headName(array $children): ?string
    {
        foreach ($children as $child) {
            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            if ($child instanceof SymbolNode) {
                return $child->getValue()->getName();
            }

            return null;
        }

        return null;
    }

    /**
     * @param list<NodeInterface> $children
     */
    private function firstVectorChild(array $children): ?ListNode
    {
        $seenHead = false;
        foreach ($children as $child) {
            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            if (!$seenHead) {
                $seenHead = true;
                continue;
            }

            if ($child instanceof ListNode && $child->getTokenType() === Token::T_OPEN_BRACKET) {
                return $child;
            }

            return null;
        }

        return null;
    }

    /**
     * @param list<NodeInterface> $children
     *
     * @return list<NodeInterface>
     */
    private function alignPairs(array $children, int $skipValueCount, bool $allowTrailingSingle = false): array
    {
        $valueIndices = [];
        foreach ($children as $i => $child) {
            if (!$child instanceof TriviaNodeInterface) {
                $valueIndices[] = $i;
            }
        }

        $pairIndices = array_slice($valueIndices, $skipValueCount);
        if ($allowTrailingSingle && count($pairIndices) % 2 === 1) {
            array_pop($pairIndices);
        }

        if (count($pairIndices) % 2 !== 0 || count($pairIndices) < 4) {
            return $children;
        }

        $pairs = [];
        $counter = count($pairIndices);
        for ($i = 0; $i < $counter; $i += 2) {
            $pairs[] = [$pairIndices[$i], $pairIndices[$i + 1]];
        }

        if (!$this->spansMultipleLines($children, $pairs)) {
            return $children;
        }

        $maxKeyWidth = 0;
        foreach ($pairs as [$keyIdx]) {
            $width = $this->keyWidth($children[$keyIdx]);
            if ($width > $maxKeyWidth) {
                $maxKeyWidth = $width;
            }
        }

        $pairByKeyIdx = [];
        foreach ($pairs as [$keyIdx, $valIdx]) {
            $pairByKeyIdx[$keyIdx] = $valIdx;
        }

        $output = [];
        $count = count($children);
        $i = 0;
        while ($i < $count) {
            $output[] = $children[$i];
            if (isset($pairByKeyIdx[$i]) && $this->gapIsHorizontal($children, $i, $pairByKeyIdx[$i])) {
                $padWidth = $maxKeyWidth - $this->keyWidth($children[$i]) + 1;
                $output[] = new WhitespaceNode(
                    str_repeat(' ', $padWidth),
                    new SourceLocation('', 0, 0),
                    new SourceLocation('', 0, 0),
                );
                $i = $pairByKeyIdx[$i];
                continue;
            }

            ++$i;
        }

        return $output;
    }

    /**
     * @param list<NodeInterface>   $children
     * @param list<array{int, int}> $pairs
     */
    private function spansMultipleLines(array $children, array $pairs): bool
    {
        for ($p = 1, $n = count($pairs); $p < $n; ++$p) {
            $prevVal = $pairs[$p - 1][1];
            $curKey = $pairs[$p][0];
            for ($k = $prevVal + 1; $k < $curKey; ++$k) {
                if ($children[$k] instanceof NewlineNode) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<NodeInterface> $children
     */
    private function gapIsHorizontal(array $children, int $keyIdx, int $valIdx): bool
    {
        for ($k = $keyIdx + 1; $k < $valIdx; ++$k) {
            $node = $children[$k];
            if ($node instanceof NewlineNode || $node instanceof CommentNode) {
                return false;
            }
        }

        return true;
    }

    private function keyWidth(NodeInterface $node): int
    {
        return strlen($node->getCode());
    }
}
