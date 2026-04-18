<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Pair;

use Phel\Compiler\Domain\Parser\ParserNode\CommentNode;
use Phel\Compiler\Domain\Parser\ParserNode\NewlineNode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\WhitespaceNode;
use Phel\Lang\SourceLocation;

use function array_slice;
use function count;
use function strlen;

/**
 * Pads horizontal whitespace between key/value pair children so values line up
 * under a common column (longest key + 1 space). Only touches pairs that span
 * multiple lines and have a simple (no newline/comment) gap between key and
 * value.
 */
final readonly class PairAligner
{
    /**
     * @param list<NodeInterface> $children
     *
     * @return list<NodeInterface>
     */
    public function align(array $children, int $skipValueCount, bool $allowTrailingSingle): array
    {
        $pairs = $this->findPairs($children, $skipValueCount, $allowTrailingSingle);
        if ($pairs === [] || !$this->spansMultipleLines($children, $pairs)) {
            return $children;
        }

        $maxKeyWidth = $this->maxKeyWidth($children, $pairs);

        return $this->rebuild($children, $pairs, $maxKeyWidth);
    }

    /**
     * @param list<NodeInterface> $children
     *
     * @return list<array{int, int}>
     */
    private function findPairs(array $children, int $skipValueCount, bool $allowTrailingSingle): array
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
            return [];
        }

        $pairs = [];
        for ($i = 0, $n = count($pairIndices); $i < $n; $i += 2) {
            $pairs[] = [$pairIndices[$i], $pairIndices[$i + 1]];
        }

        return $pairs;
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
     * @param list<NodeInterface>   $children
     * @param list<array{int, int}> $pairs
     */
    private function maxKeyWidth(array $children, array $pairs): int
    {
        $max = 0;
        foreach ($pairs as [$keyIdx]) {
            $width = strlen($children[$keyIdx]->getCode());
            if ($width > $max) {
                $max = $width;
            }
        }

        return $max;
    }

    /**
     * @param list<NodeInterface>   $children
     * @param list<array{int, int}> $pairs
     *
     * @return list<NodeInterface>
     */
    private function rebuild(array $children, array $pairs, int $maxKeyWidth): array
    {
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
                $padWidth = $maxKeyWidth - strlen($children[$i]->getCode()) + 1;
                $output[] = $this->makeWhitespace($padWidth);
                $i = $pairByKeyIdx[$i];
                continue;
            }

            ++$i;
        }

        return $output;
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

    private function makeWhitespace(int $width): WhitespaceNode
    {
        return new WhitespaceNode(
            str_repeat(' ', $width),
            new SourceLocation('', 0, 0),
            new SourceLocation('', 0, 0),
        );
    }
}
