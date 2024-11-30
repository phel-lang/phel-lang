<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Zipper;

/**
 * @template T
 *
 * @psalm-consistent-constructor
 */
abstract class AbstractZipper
{
    /**
     * @param T $node
     * @param ?AbstractZipper<T> $parent
     * @param list<T> $leftSiblings
     * @param list<T> $rightSiblings
     */
    final public function __construct(
        protected mixed $node,
        protected ?self $parent,
        protected array $leftSiblings = [],
        protected array $rightSiblings = [],
        protected bool $hasChanged = false,
        protected bool $isEnd = false,
    ) {
    }

    /**
     * @return list<T>
     */
    abstract public function getChildren(): array;

    abstract public function isBranch(): bool;

    /**
     * @param T $node
     * @param list<T> $children
     *
     * @return T
     */
    abstract public function makeNode(mixed $node, array $children);

    /**
     * @throws ZipperException
     *
     * @return static<T>
     */
    public function left(): self
    {
        if ($this->isTop()) {
            throw ZipperException::cannotGoLeftOnRootNode();
        }

        if ($this->isFirst()) {
            throw ZipperException::cannotGoLeftOnTheLeftmostNode();
        }

        $leftSiblings = $this->leftSiblings;
        $left = array_pop($leftSiblings);
        return $this->createNewInstance(
            $left,
            $this->parent,
            $leftSiblings,
            [$this->node, ...$this->rightSiblings],
            $this->hasChanged,
            false,
        );
    }

    /**
     * @return static<T>
     */
    public function leftMost(): self
    {
        $loc = $this;
        while (!$loc->isFirst()) {
            $loc = $loc->left();
        }

        return $loc;
    }

    /**
     * @return list<T>
     */
    public function lefts(): array
    {
        return $this->leftSiblings;
    }

    /**
     * @return static<T>
     */
    public function right(): self
    {
        if ($this->isTop()) {
            throw ZipperException::cannotGoRightOnRootNode();
        }

        if ($this->isLast()) {
            throw ZipperException::cannotGoRightOnLastNode();
        }

        $rightSiblings = $this->rightSiblings;
        $right = array_shift($rightSiblings);
        return $this->createNewInstance(
            $right,
            $this->parent,
            [...$this->leftSiblings, $this->node],
            $rightSiblings,
            $this->hasChanged,
            false,
        );
    }

    /**
     * @return static<T>
     */
    public function rightMost(): self
    {
        $loc = $this;
        while (!$loc->isLast()) {
            $loc = $loc->right();
        }

        return $loc;
    }

    /**
     * @return list<T>
     */
    public function rights(): array
    {
        return $this->rightSiblings;
    }

    /**
     * @return static<T>
     */
    public function up(): self
    {
        if ($this->isTop()) {
            throw ZipperException::cannotGoUpOnRootNode();
        }

        if ($this->hasChanged) {
            $newParent = $this->makeNode(
                $this->parent->getNode(),
                [...$this->leftSiblings, $this->node, ...$this->rightSiblings],
            );

            return $this->createNewInstance(
                $newParent,
                $this->parent->parent,
                $this->parent->lefts(),
                $this->parent->rights(),
                true,
                $this->parent->isEnd(),
            );
        }

        /** @var static<T> $parent */
        $parent = clone $this->parent;
        return $parent;
    }

    /**
     * @return T
     */
    public function root()
    {
        if ($this->isEnd) {
            return $this->node;
        }

        $loc = $this;
        while (!$loc->isTop()) {
            $loc = $loc->up();
        }

        return $loc->getNode();
    }

    /**
     * @return static<T>
     */
    public function down(): self
    {
        if (!$this->isBranch()) {
            throw ZipperException::cannotGoDownOnLeafNode();
        }

        $children = $this->getChildren();
        if ($children === []) {
            throw ZipperException::cannotGoDownOnNodeWithZeroChildren();
        }

        $leftChild = array_shift($children);

        return $this->createNewInstance(
            $leftChild,
            $this,
            [],
            $children,
            false,
            false,
        );
    }

    /**
     * @return static<T>
     */
    public function next(): self
    {
        if ($this->isEnd) {
            return $this;
        }

        if ($this->hasChildren()) {
            return $this->down();
        }

        if (!$this->isLast()) {
            return $this->right();
        }

        $up = $this;
        while ($up->isLast() && !$up->isTop()) {
            $up = $up->up();
        }

        if ($up->isTop()) {
            $up->isEnd = true;
            return $up;
        }

        return $up->right();
    }

    /**
     * @return static<T>
     */
    public function prev(): self
    {
        if (!$this->isFirst()) {
            $loc = $this->left();
            while ($loc->hasChildren()) {
                $loc = $loc->down()->rightMost();
            }

            /** @var static<T> $loc */
            return $loc;
        }

        return $this->up();
    }

    /**
     * @return T
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * @param T $node
     */
    public function setNode(mixed $node): self
    {
        $this->node = $node;

        return $this;
    }

    public function setHasChanged(bool $status): self
    {
        $this->hasChanged = $status;

        return $this;
    }

    /**
     * @param T $node
     *
     * @throws ZipperException
     */
    public function insertLeft(mixed $node): static
    {
        if ($this->isTop()) {
            throw ZipperException::cannotInsertLeftOnRootNode();
        }

        $this->hasChanged = true;
        $this->leftSiblings = [...$this->leftSiblings, $node];

        return $this;
    }

    /**
     * @param T $node
     *
     * @throws ZipperException
     */
    public function insertRight(mixed $node): static
    {
        if ($this->isTop()) {
            throw ZipperException::cannotInsertRightOnRootNode();
        }

        $this->hasChanged = true;
        $this->rightSiblings = [$node, ...$this->rightSiblings];

        return $this;
    }

    /**
     * @param T $node
     *
     * @return static<T>
     */
    public function replace(mixed $node): static
    {
        $this->hasChanged = true;
        $this->node = $node;

        return $this;
    }

    /**
     * @param T $node
     *
     * @return static<T>
     */
    public function insertChild(mixed $node): static
    {
        return $this->replace(
            $this->makeNode($this->node, [$node, ...$this->getChildren()]),
        );
    }

    /**
     * @param T $node
     *
     * @return static<T>
     */
    public function appendChild(mixed $node): static
    {
        return $this->replace(
            $this->makeNode($this->node, [...$this->getChildren(), $node]),
        );
    }

    /**
     * @throws ZipperException
     *
     * @return static<T>
     */
    public function remove(): self
    {
        if ($this->isTop()) {
            throw ZipperException::cannotRemoveOnRootNode();
        }

        if (!$this->isFirst()) {
            $leftSiblings = $this->leftSiblings;
            $left = array_pop($leftSiblings);
            $loc = $this->createNewInstance(
                $left,
                $this->parent,
                $leftSiblings,
                $this->rightSiblings,
                true,
                false,
            );
            while ($loc->isBranch() && $loc->hasChildren() && (($child = $loc->down()) instanceof self)) {
                $loc = $child->rightMost();
            }

            /** @var static<T> $loc */
            return $loc;
        }

        return $this->createNewInstance(
            $this->makeNode($this->parent->getNode(), $this->rightSiblings),
            $this->parent->parent,
            $this->parent->lefts(),
            $this->parent->rights(),
            true,
            $this->parent->isEnd(),
        );
    }

    public function isEnd(): bool
    {
        return $this->isEnd;
    }

    public function hasChildren(): bool
    {
        return $this->isBranch() && $this->getChildren() !== [];
    }

    /**
     * @psalm-assert-if-false AbstractZipper<T> $this->parent
     */
    public function isTop(): bool
    {
        return !$this->parent instanceof self;
    }

    public function isFirst(): bool
    {
        return $this->leftSiblings === [];
    }

    /**
     * @psalm-assert non-empty-list $this->rightSiblings
     */
    public function isLast(): bool
    {
        return $this->rightSiblings === [];
    }

    /**
     * @param T $node
     * @param ?AbstractZipper<T> $parent
     * @param list<T> $leftSiblings
     * @param list<T> $rightSiblings
     *
     * @return static
     */
    abstract protected function createNewInstance(
        mixed $node,
        ?self $parent,
        array $leftSiblings,
        array $rightSiblings,
        bool $hasChanged,
        bool $isEnd,
    );
}
