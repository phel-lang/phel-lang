<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules;

use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;
use Phel\Shared\Parser\Node\NodeInterface;

/**
 * Collapses runs of consecutive blank lines into a single blank line, matching
 * cljfmt's `:remove-consecutive-blank-lines?` (on by default).
 *
 * The lexer emits one {@see \Phel\Shared\Parser\Node\NewlineNode} per `\n`, so a
 * blank line is two adjacent newline nodes. Anything beyond the second
 * consecutive newline is removed, leaving at most one empty line between forms.
 * Runs after {@see UnindentRule}, which strips the indentation whitespace that
 * would otherwise sit between the newlines of an "empty" line.
 */
final readonly class RemoveConsecutiveBlankLinesRule implements RuleInterface
{
    /** One newline ends a line; a second produces a single blank line. */
    private const int MAX_CONSECUTIVE_NEWLINES = 2;

    /**
     * @throws ZipperException
     */
    public function transform(NodeInterface $node): NodeInterface
    {
        return $this->removeConsecutiveBlankLines(ParseTreeZipper::createRoot($node));
    }

    /**
     * @throws ZipperException
     */
    private function removeConsecutiveBlankLines(ParseTreeZipper $loc): NodeInterface
    {
        $node = $loc;
        while (!$node->isEnd()) {
            /** @var ParseTreeZipper $node */
            $node = $node->next();
            if ($this->isExtraBlankLine($node)) {
                /** @var ParseTreeZipper $node */
                $node = $node->remove();
            }
        }

        return $node->root();
    }

    private function isExtraBlankLine(ParseTreeZipper $loc): bool
    {
        return $loc->isNewline()
            && $this->precedingLineBreakCount($loc) >= self::MAX_CONSECUTIVE_NEWLINES;
    }

    /**
     * Counts consecutive line breaks immediately to the left of $loc. A comment
     * node carries its own trailing newline, so it counts as one line break and
     * ends the run (a comment is content, not blank space).
     */
    private function precedingLineBreakCount(ParseTreeZipper $loc): int
    {
        $count = 0;
        $cursor = $loc;
        while (!$cursor->isFirst()) {
            $cursor = $cursor->left();
            if ($cursor->isNewline()) {
                ++$count;
                continue;
            }

            if ($cursor->isComment()) {
                ++$count;
            }

            break;
        }

        return $count;
    }
}
