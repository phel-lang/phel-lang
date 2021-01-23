<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Phel\Compiler\Parser\ParserNode\CommentNode;
use Phel\Compiler\Parser\ParserNode\InnerNodeInterface;
use Phel\Compiler\Parser\ParserNode\NewlineNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Parser\ParserNode\WhitespaceNode;
use Phel\Exceptions\ZipperException;

/** @extends AbstractZipper<NodeInterface> */
final class ParseTreeZipper extends AbstractZipper
{
    /**
     * @psalm-assert-if-true InnerNodeInterface $this->node
     */
    public function isBranch(): bool
    {
        return $this->node instanceof InnerNodeInterface;
    }

    public function getChildren()
    {
        if (!$this->isBranch()) {
            throw new ZipperException('called children on leaf node');
        }

        return $this->node->getChildren();
    }

    public function makeNode($node, $children)
    {
        if (!$node instanceof InnerNodeInterface) {
            throw new ZipperException('can replace children on leaf node');
        }

        /** @var InnerNodeInterface $node */
        return $node->replaceChildren($children);
    }

    public function isLineBreak(): bool
    {
        return $this->getNode() instanceof NewlineNode || $this->isComment();
    }

    public function isNewline(): bool
    {
        return $this->getNode() instanceof NewlineNode;
    }

    public function isWhitespaceOrNewline(): bool
    {
        return $this->isNewline() || $this->isWhitespace();
    }

    public function isWhitespace(): bool
    {
        return $this->getNode() instanceof WhitespaceNode;
    }

    public function isComment(): bool
    {
        return $this->getNode() instanceof CommentNode;
    }

    public function leftSkipWhitespace(): ParseTreeZipper
    {
        return $this->left()->skipWhitespaceLeft();
    }

    public function skipWhitespaceLeft(): ParseTreeZipper
    {
        $loc = $this;
        while ($loc->getNode() instanceof TriviaNodeInterface) {
            $loc = $loc->left();
        }

        return $loc;
    }

    public function rightSkipWhitespace(): ParseTreeZipper
    {
        return $this->right()->skipWhitespaceRight();
    }

    public function skipWhitespaceRight(): ParseTreeZipper
    {
        $loc = $this;
        while ($loc->getNode() instanceof TriviaNodeInterface) {
            $loc = $loc->right();
        }

        return $loc;
    }

    public function upSkipWhitespace(): ParseTreeZipper
    {
        return $this->up()->skipWhitespaceLeft();
    }

    public function downSkipWhitespace(): ParseTreeZipper
    {
        return $this->down()->skipWhitespaceRight();
    }

    public function leftMostSkipWhitespace(): ParseTreeZipper
    {
        return $this->leftMost()->skipWhitespaceRight();
    }

    public function rightMostSkipWhitespace(): ParseTreeZipper
    {
        return $this->rightMost()->skipWhitespaceLeft();
    }

    public function nextSkipWhitespace(): ParseTreeZipper
    {
        $loc = $this->next();
        while (!$loc->isEnd() && $loc->getNode() instanceof TriviaNodeInterface) {
            $nextLoc = $loc->next();

            if ($nextLoc->isEnd()) {
                return $loc;
            }

            $loc = $nextLoc;
        }

        return $loc;
    }
}
