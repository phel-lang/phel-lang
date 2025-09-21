<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules;

use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;

final class RemoveTrailingWhitespaceRule implements RuleInterface
{
    public function transform(NodeInterface $node): NodeInterface
    {
        return $this->removeTrailingWhitespace(ParseTreeZipper::createRoot($node));
    }

    /**
     * @throws ZipperException
     */
    private function removeTrailingWhitespace(ParseTreeZipper $loc): NodeInterface
    {
        /** @var ParseTreeZipper $node */
        $node = $loc;
        while (!$node->isEnd()) {
            /** @var ParseTreeZipper $node */
            $node = $node->next();
            if ($this->isTrailingWhitespace($node)) {
                /** @var ParseTreeZipper $node */
                $node = $node->remove();
            }
        }

        return $node->root();
    }

    private function isTrailingWhitespace(ParseTreeZipper $loc): bool
    {
        return (
            $loc->isWhitespace()
            && ($loc->isLast() || $loc->right()->isNewline())
        );
    }
}
