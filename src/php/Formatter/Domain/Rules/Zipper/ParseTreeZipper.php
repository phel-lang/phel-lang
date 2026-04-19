<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Zipper;

use Override;
use Phel\Compiler\Domain\Parser\ParserNode\CommentNode;
use Phel\Compiler\Domain\Parser\ParserNode\InnerNodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\NewlineNode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\WhitespaceNode;

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

    public function makeNode(mixed $node, array $children): InnerNodeInterface
    {
        if (!$node instanceof InnerNodeInterface) {
            throw ZipperException::cannotReplaceChildrenOnLeafNode();
        }

        return $node->replaceChildren($children);
    }

    #[Override]
    public function isLineBreak(): bool
    {
        return $this->getNode() instanceof NewlineNode || $this->isComment();
    }

    #[Override]
    public function isNewline(): bool
    {
        return $this->getNode() instanceof NewlineNode;
    }

    public function isWhitespaceOrNewline(): bool
    {
        if ($this->isNewline()) {
            return true;
        }

        return $this->isWhitespace();
    }

    public function isWhitespace(): bool
    {
        return $this->getNode() instanceof WhitespaceNode;
    }

    public function isComment(): bool
    {
        return $this->getNode() instanceof CommentNode;
    }

    #[Override]
    public function skipWhitespaceLeft(): static
    {
        $loc = $this;
        while ($loc->getNode() instanceof TriviaNodeInterface) {
            $loc = $loc->left();
        }

        return $loc;
    }

    #[Override]
    public function rightSkipWhitespace(): static
    {
        return $this->right()->skipWhitespaceRight();
    }

    #[Override]
    public function skipWhitespaceRight(): static
    {
        $loc = $this;
        while ($loc->getNode() instanceof TriviaNodeInterface) {
            $loc = $loc->right();
        }

        return $loc;
    }

    public function upSkipWhitespace(): static
    {
        return $this->up()->skipWhitespaceLeft();
    }

    public function leftMostSkipWhitespace(): static
    {
        return $this->leftMost()->skipWhitespaceRight();
    }

    /**
     * @param NodeInterface                  $node
     * @param ?AbstractZipper<NodeInterface> $parent
     * @param list<NodeInterface>            $leftSiblings
     * @param list<NodeInterface>            $rightSiblings
     */
    protected function createNewInstance(
        $node,
        ?AbstractZipper $parent,
        array $leftSiblings,
        array $rightSiblings,
        bool $hasChanged,
        bool $isEnd,
    ): static {
        return new self($node, $parent, $leftSiblings, $rightSiblings, $hasChanged, $isEnd);
    }
}
