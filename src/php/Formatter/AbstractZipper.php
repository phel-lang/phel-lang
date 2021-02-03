<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Exception;
use Phel\Formatter\Exceptions\CanNotRemoveAtTheTopException;
use Phel\Formatter\Exceptions\ZipperException;

/**
 * @template T
 */
abstract class AbstractZipper
{
    /** @var T */
    protected $node;
    /** @var ?AbstractZipper<T> */
    protected ?AbstractZipper $parent;
    /** @var T[] */
    protected array $leftSiblings;
    /** @var T[] */
    protected array $rightSiblings;
    protected bool $hasChanged = false;
    protected bool $isEnd = false;

    /**
     * @param T $node
     * @param ?AbstractZipper<T> $parent
     * @param T[] $leftSiblings
     * @param T[] $rightSiblings
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
     * @template U
     *
     * @param U $root
     *
     * @return static<U>
     */
    final public static function createRoot($root): AbstractZipper
    {
        return new static($root, null, [], [], false, false);
    }

    /**
     * @return T[]
     */
    abstract public function getChildren();

    abstract public function isBranch(): bool;

    /**
     * @param T $node
     * @param T[] $children
     *
     * @return T
     */
    abstract public function makeNode($node, $children);

    /**
     * @return static<T>
     */
    public function left(): AbstractZipper
    {
        if ($this->isTop()) {
            throw new ZipperException('Can not go left on the root node');
        }

        if ($this->isFirst()) {
            throw new ZipperException('Can not go left on the leftmost node');
        }

        $leftSiblings = $this->leftSiblings;
        $left = array_pop($leftSiblings);
        return new static(
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
     * @return T[]
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
            throw new ZipperException('Can not go right on the root node');
        }

        if ($this->isLast()) {
            throw new ZipperException('Can not go right on the rightmost node');
        }

        $rightSiblings = $this->rightSiblings;
        $right = array_shift($rightSiblings);
        return new static(
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
     * @return T[]
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
            throw new ZipperException('Can not go up on the root node');
        }

        if ($this->hasChanged) {
            $newParent = $this->makeNode(
                $this->parent->getNode(),
                [...$this->leftSiblings, $this->node, ...$this->rightSiblings]
            );

            return new static(
                $newParent,
                $this->parent->parent,
                $this->parent->lefts(),
                $this->parent->rights(),
                true,
                $this->parent->isEnd()
            );
        }

        /** @var static<T> */
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
            throw new ZipperException('Can not go down on a leaf node');
        }

        $children = $this->getChildren();
        if (count($children) == 0) {
            throw new ZipperException('Can not go down on a node with zero children');
        }

        $leftChild = array_shift($children);

        return new static(
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
     * @return static
     */
    public function insertLeft($node)
    {
        if ($this->isTop()) {
            throw new Exception('Can not insert left at the top');
        }

        $this->hasChanged = true;
        $this->leftSiblings = [...$this->leftSiblings, $node];

        return $this;
    }

    /**
     * @param T $node
     *
     * @return static
     */
    public function insertRight($node)
    {
        if ($this->isTop()) {
            throw new Exception('Can not insert right at the top');
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
     * @throws CanNotRemoveAtTheTopException
     *
     * @return static<T>
     */
    public function remove(): AbstractZipper
    {
        if ($this->isTop()) {
            throw new CanNotRemoveAtTheTopException();
        }

        if (!$this->isFirst()) {
            $leftSiblings = $this->leftSiblings;
            $left = array_pop($leftSiblings);
            $loc = new static(
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

        return new static(
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
     * @psalm-assert-if-false static<T> $this->parent
     */
    public function isTop(): bool
    {
        return $this->parent === null;
    }

    /**
     * @psalm-assert non-empty-list $this->leftSiblings
     */
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
