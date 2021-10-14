<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Zipper;

use Phel\Compiler\Parser\ParserNode\CommentNode;
use Phel\Compiler\Parser\ParserNode\InnerNodeInterface;
use Phel\Compiler\Parser\ParserNode\NewlineNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Parser\ParserNode\WhitespaceNode;

/**
 * @extends AbstractZipper<NodeInterface>
 */
final class ParseTreeZipper extends AbstractZipper
{
    public static function createRoot(NodeInterface $root): self
    {
        return new self($root, null, [], [], false, false);
    }

    /**
     * @param NodeInterface $node
     * @param ?AbstractZipper<NodeInterface> $parent
     * @param list<NodeInterface> $leftSiblings
     * @param list<NodeInterface> $rightSiblings
     */
    protected function createNewInstance(
        $node,
        ?AbstractZipper $parent,
        array $leftSiblings,
        array $rightSiblings,
        bool $hasChanged,
        bool $isEnd
    ): self {
        return new self($node, $parent, $leftSiblings, $rightSiblings, $hasChanged, $isEnd);
    }

    /**
     * @psalm-assert-if-true InnerNodeInterface $this->node
     */
    public function isBranch(): bool
    {
        return $this->node instanceof InnerNodeInterface;
    }

    public function getChildren(): array
    {
        if (!$this->isBranch()) {
            throw ZipperException::calledChildrenOnLeafNode();
        }

        return $this->node->getChildren();
    }

    public function makeNode($node, $children)
    {
        if (!$node instanceof InnerNodeInterface) {
            throw ZipperException::cannotReplaceChildrenOnLeafNode();
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
