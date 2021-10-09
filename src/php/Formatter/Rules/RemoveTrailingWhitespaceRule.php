<?php

declare(strict_types=1);

namespace Phel\Formatter\Rules;

use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Formatter\Exceptions\ZipperException;
use Phel\Formatter\Formatter\ParseTreeZipper;

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
        $node = $loc;
        while (!$node->isEnd()) {
            $node = $node->next();
            if ($this->isTrailingWhitespace($node)) {
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
