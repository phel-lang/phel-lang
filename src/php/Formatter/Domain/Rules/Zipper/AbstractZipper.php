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
    /** @var T */
    protected $node;
    /** @var ?AbstractZipper<T> */
    protected ?AbstractZipper $parent;
    /** @var list<T> */
    protected array $leftSiblings = [];
    /** @var list<T> */
    protected array $rightSiblings = [];
    protected bool $hasChanged = false;
    protected bool $isEnd = false;

    /**
     * @param T $node
     * @param ?AbstractZipper<T> $parent
     * @param list<T> $leftSiblings
     * @param list<T> $rightSiblings
     */
    final public function __construct(
        $node,
        ?AbstractZipper $parent,
        array $leftSiblings,
        array $rightSiblings,
        bool $hasChanged,
        bool $isEnd
    ) {
        $this->node = $node;
        $this->parent = $parent;
        $this->leftSiblings = $leftSiblings;
        $this->rightSiblings = $rightSiblings;
        $this->hasChanged = $hasChanged;
        $this->isEnd = $isEnd;
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
        $node,
        ?AbstractZipper $parent,
        array $leftSiblings,
        array $rightSiblings,
        bool $hasChanged,
        bool $isEnd
    );

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
    abstract public function makeNode($node, $children);

    /**
     * @throws ZipperException
     *
     * @return static<T>
     */
    public function left(): AbstractZipper
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
            false
        );
    }

    /**
     * @return static<T>
     */
    public function leftMost(): AbstractZipper
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
    public function right(): AbstractZipper
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
            false
        );
    }

    /**
     * @return static<T>
     */
    public function rightMost(): AbstractZipper
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
    public function up(): AbstractZipper
    {
        if ($this->isTop()) {
            throw ZipperException::cannotGoUpOnRootNode();
        }

        if ($this->hasChanged) {
            $newParent = $this->makeNode(
                $this->parent->getNode(),
                [...$this->leftSiblings, $this->node, ...$this->rightSiblings]
            );

            return $this->createNewInstance(
                $newParent,
                $this->parent->parent,
                $this->parent->lefts(),
                $this->parent->rights(),
                true,
                $this->parent->isEnd()
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
        if ($this->isEnd()) {
            return $this->getNode();
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
    public function down(): AbstractZipper
    {
        if (!$this->isBranch()) {
            throw ZipperException::cannotGoDownOnLeafNode();
        }

        $children = $this->getChildren();
        if (count($children) === 0) {
            throw ZipperException::cannotGoDownOnNodeWithZeroChildren();
        }

        $leftChild = array_shift($children);

        return $this->createNewInstance(
            $leftChild,
            $this,
            [],
            $children,
            false,
            false
        );
    }

    /**
     * @return static<T>
     */
    public function next(): AbstractZipper
    {
        if ($this->isEnd()) {
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
    public function prev(): AbstractZipper
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
    public function setNode($node): self
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
     *
     * @return static
     */
    public function insertLeft($node)
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
     *
     * @return static
     */
    public function insertRight($node)
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
    public function replace($node)
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
    public function insertChild($node)
    {
        return $this->replace(
            $this->makeNode($this->node, [$node, ...$this->getChildren()])
        );
    }

    /**
     * @param T $node
     *
     * @return static<T>
     */
    public function appendChild($node)
    {
        return $this->replace(
            $this->makeNode($this->node, [...$this->getChildren(), $node])
        );
    }

    /**
     * @throws ZipperException
     *
     * @return static<T>
     */
    public function remove(): AbstractZipper
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
                false
            );
            while ($loc->isBranch() && $loc->hasChildren() && ($child = $loc->down())) {
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
            $this->parent->isEnd()
        );
    }

    public function isEnd(): bool
    {
        return $this->isEnd;
    }

    public function hasChildren(): bool
    {
        return $this->isBranch() && count($this->getChildren()) > 0;
    }

    /**
     * @psalm-assert-if-false AbstractZipper<T> $this->parent
     */
    public function isTop(): bool
    {
        return $this->parent === null;
    }

    public function isFirst(): bool
    {
        return empty($this->leftSiblings);
    }

    /**
     * @psalm-assert non-empty-list $this->rightSiblings
     */
    public function isLast(): bool
    {
        return empty($this->rightSiblings);
    }
}
