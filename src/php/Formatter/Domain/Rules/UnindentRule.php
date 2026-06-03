<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules;

use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;
use Phel\Shared\Parser\Node\NodeInterface;

/**
 * Removes all existing indentation: every whitespace node that directly follows
 * a line break is stripped (unless the next non-whitespace node is a comment,
 * whose leading indentation is preserved).
 *
 * Runs as a preprocessor for IndentRule to normalize the tree to a known,
 * unindented state. This is safe because IndentRule then recalculates every
 * line's indentation from scratch.
 */
final class UnindentRule implements RuleInterface
{
    public function transform(NodeInterface $node): NodeInterface
    {
        return $this->unident(ParseTreeZipper::createRoot($node));
    }

    /**
     * @throws ZipperException
     */
    private function unident(ParseTreeZipper $form): NodeInterface
    {
        $node = $form;
        while (!$node->isEnd()) {
            /** @var ParseTreeZipper $node */
            $node = $node->next();
            if ($this->shouldUnindent($node)) {
                /** @var ParseTreeZipper $node */
                $node = $node->remove();
            }
        }

        return $node->root();
    }

    private function shouldUnindent(ParseTreeZipper $form): bool
    {
        return $this->isIndention($form) && !$this->isNextComment($form);
    }

    private function skipWhitespace(ParseTreeZipper $form): ParseTreeZipper
    {
        $node = $form;
        while ($node->isWhitespace()) {
            /** @var ParseTreeZipper $node */
            $node = $node->next();
        }

        return $node;
    }

    private function isNextComment(ParseTreeZipper $form): bool
    {
        return $this->skipWhitespace($form->next())->isComment();
    }

    private function isIndention(ParseTreeZipper $form): bool
    {
        try {
            return $form->prev()->isLineBreak() && $form->isWhitespace();
        } catch (ZipperException) {
            return false;
        }
    }
}
