<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules;

use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;

final class RemoveSurroundingWhitespaceRule implements RuleInterface
{
    /**
     * @throws ZipperException
     */
    public function transform(NodeInterface $node): NodeInterface
    {
        return $this->removeSurroundingWhitespace(ParseTreeZipper::createRoot($node));
    }

    /**
     * @throws ZipperException
     */
    private function removeSurroundingWhitespace(ParseTreeZipper $loc): NodeInterface
    {
        $node = $loc;
        while (!$node->isEnd()) {
            $node = $node->next();
            if ($this->isSurroundingWhitespace($node)) {
                $node = $node->remove();
            }
        }

        return $node->root();
    }

    private function isSurroundingWhitespace(ParseTreeZipper $loc): bool
    {
        return (
            !$loc->isTop()
            && $this->isTop($loc->up())
            && $loc->isWhitespaceOrNewline()
            && ($loc->isFirst() || $this->isRestWhitespace($loc)) //$loc->isLast())
        );
    }

    private function isRestWhitespace(ParseTreeZipper $loc): bool
    {
        $l = $loc;
        while ($l->isWhitespaceOrNewline() && !$l->isLast()) {
            $l = $l->right();
        }

        return $l->isWhitespaceOrNewline() && $l->isLast();
    }

    private function isTop(ParseTreeZipper $loc): bool
    {
        return $loc->getNode() !== $loc->root();
    }
}
