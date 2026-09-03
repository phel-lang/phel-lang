<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Zipper;

use Phel\Shared\Parser\Node\NodeInterface;
use RuntimeException;

use function array_slice;
use function array_splice;
use function assert;
use function count;

/**
 * Functional zipper for navigating and editing an immutable parse tree.
 *
 * A zipper represents a "location": the focused {@see self::$node} plus enough
 * context (its $parent, and the $siblings around it) to walk in any direction
 * and rebuild the whole tree. Movement methods (left/right/up/down/next/prev)
 * return a *new* location rather than mutating in place, so traversal is
 * side-effect free.
 *
 * A location holds the parent's whole child list and its own $index in it,
 * not two "left" and "right" arrays. Moving sideways is then a new location
 * over the same array, O(1), where copying the two halves made a walk along
 * n siblings cost O(n^2): a generated data file with one 16 000-element
 * literal took 28 s to format (#3218). Only an edit copies the list (PHP
 * arrays: O(n) per insert or removal), and the parent is rebuilt once on
 * the way {@see self::up()}.
 *
 * Edits (replace/insert/remove) set the {@see self::$hasChanged} flag on the
 * location. When {@see self::up()} is called on a changed location,
 * {@see self::reconstructParentFromChange()} rebuilds the parent node from the
 * current siblings so the edit propagates upward; unchanged locations simply
 * return the cached parent. {@see self::$isEnd} marks a completed depth-first
 * walk (see {@see self::next()}).
 *
 * @template T of NodeInterface
 *
 * @psalm-consistent-constructor
 *
 * @internal
 */
abstract class AbstractZipper
{
    /**
     * @param T                  $node
     * @param ?AbstractZipper<T> $parent
     * @param list<T>            $siblings every child of the parent, this node included at $index (empty for the root)
     */
    final public function __construct(
        protected mixed $node,
        protected ?self $parent,
        protected array $siblings = [],
        protected int $index = 0,
        protected bool $hasChanged = false,
        protected bool $isEnd = false,
    ) {}

    /**
     * @return list<T>
     */
    abstract public function getChildren(): array;

    abstract public function isBranch(): bool;

    /**
     * @param T       $node
     * @param list<T> $children
     *
     * @return T
     */
    abstract public function makeNode(mixed $node, array $children);

    public function skipWhitespaceRight(): static
    {
        return $this;
    }

    public function rightSkipWhitespace(): static
    {
        return $this->right()->skipWhitespaceRight();
    }

    public function isLineBreak(): bool
    {
        return false;
    }

    public function isNewline(): bool
    {
        return false;
    }

    public function skipWhitespaceLeft(): static
    {
        return $this;
    }

    /**
     * @throws ZipperException
     *
     * @return static<T>
     */
    public function left(): static
    {
        if ($this->isTop()) {
            throw ZipperException::cannotGoLeftOnRootNode();
        }

        if ($this->isFirst()) {
            throw ZipperException::cannotGoLeftOnTheLeftmostNode();
        }

        return $this->sibling($this->index - 1);
    }

    public function leftMost(): static
    {
        return $this->isFirst() ? $this : $this->sibling(0);
    }

    /**
     * @return list<T>
     */
    public function lefts(): array
    {
        return array_slice($this->siblings, 0, $this->index);
    }

    public function right(): static
    {
        if ($this->isTop()) {
            throw ZipperException::cannotGoRightOnRootNode();
        }

        if ($this->isLast()) {
            throw ZipperException::cannotGoRightOnLastNode();
        }

        return $this->sibling($this->index + 1);
    }

    public function rightMost(): static
    {
        return $this->isLast() ? $this : $this->sibling(count($this->siblings) - 1);
    }

    /**
     * @return list<T>
     */
    public function rights(): array
    {
        return array_slice($this->siblings, $this->index + 1);
    }

    public function up(): static
    {
        if ($this->isTop()) {
            throw ZipperException::cannotGoUpOnRootNode();
        }

        assert($this->parent instanceof self);

        if ($this->hasChanged) {
            return $this->reconstructParentFromChange();
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
        $loc = $this;

        while (!$loc->isTop()) {
            $loc = $loc->up();
        }

        return $loc->getNode();
    }

    public function down(): static
    {
        if (!$this->isBranch()) {
            throw ZipperException::cannotGoDownOnLeafNode();
        }

        $children = $this->getChildren();
        if ($children === []) {
            throw ZipperException::cannotGoDownOnNodeWithZeroChildren();
        }

        return $this->createNewInstance($children[0], $this, $children, 0, false, false);
    }

    public function next(): static
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

        return $this->backtrackToNextSibling();
    }

    public function prev(): static
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
     *
     * @throws ZipperException
     */
    public function insertLeft(mixed $node): static
    {
        if ($this->isTop()) {
            throw ZipperException::cannotInsertLeftOnRootNode();
        }

        $this->hasChanged = true;
        array_splice($this->siblings, $this->index, 0, [$node]);
        ++$this->index;

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
        array_splice($this->siblings, $this->index + 1, 0, [$node]);

        return $this;
    }

    /**
     * @param T $node
     */
    public function replace(mixed $node): static
    {
        $this->hasChanged = true;
        $this->node = $node;

        return $this;
    }

    /**
     * @param T $node
     */
    public function insertChild(mixed $node): static
    {
        return $this->replace(
            $this->makeNode($this->node, [$node, ...$this->getChildren()]),
        );
    }

    /**
     * @param T $node
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
     * @return self<T>
     */
    public function remove(): self
    {
        if ($this->isTop()) {
            throw ZipperException::cannotRemoveOnRootNode();
        }

        assert($this->parent instanceof self);

        return $this->isFirst()
            ? $this->removeFirstChild()
            : $this->removeNonFirstSibling();
    }

    public function isEnd(): bool
    {
        return $this->isEnd;
    }

    public function hasChildren(): bool
    {
        return $this->isBranch() && $this->getChildren() !== [];
    }

    public function isTop(): bool
    {
        return !$this->parent instanceof static;
    }

    public function isFirst(): bool
    {
        return $this->index === 0;
    }

    public function isLast(): bool
    {
        return $this->index >= count($this->siblings) - 1;
    }

    /**
     * @param T                  $node
     * @param ?AbstractZipper<T> $parent
     * @param list<T>            $siblings
     */
    abstract protected function createNewInstance(
        mixed $node,
        ?self $parent,
        array $siblings,
        int $index,
        bool $hasChanged,
        bool $isEnd,
    ): static;

    /**
     * The location of the sibling at `$index`, carrying this location's edits.
     *
     * @return static<T>
     */
    private function sibling(int $index): static
    {
        $siblings = $this->siblingsWithNode();

        /** @var static<T> $newInstance */
        $newInstance = $this->createNewInstance($siblings[$index], $this->parent, $siblings, $index, $this->hasChanged, false);

        return $newInstance;
    }

    /**
     * The sibling list with the focused node written back into its slot.
     * {@see self::replace()} only swaps {@see self::$node}, so a replaced
     * node reaches the list here, once, the first time something reads it;
     * every other read is the identity check alone.
     *
     * @return list<T>
     */
    private function siblingsWithNode(): array
    {
        if ($this->siblings === [] || $this->siblings[$this->index] === $this->node) {
            return $this->siblings;
        }

        return [
            ...array_slice($this->siblings, 0, $this->index),
            $this->node,
            ...array_slice($this->siblings, $this->index + 1),
        ];
    }

    /**
     * Rebuilds the parent node from the current (changed) siblings and
     * returns a new zipper positioned at that parent, preserving the
     * parent's own siblings/end state and marking the result as changed.
     *
     * @return static<T>
     */
    private function reconstructParentFromChange(): static
    {
        assert($this->parent instanceof self);

        $newParent = $this->makeNode($this->parent->getNode(), $this->siblingsWithNode());

        /** @var static<T> $newInstance */
        $newInstance = $this->createNewInstance(
            $newParent,
            $this->parent->parent,
            $this->parent->siblings,
            $this->parent->index,
            true,
            $this->parent->isEnd(),
        );

        return $newInstance;
    }

    /**
     * Walks up the tree while the current location is the last sibling,
     * stopping at the next ancestor that has a right sibling. If the walk
     * reaches the root, marks the zipper as ended and returns it.
     *
     * @return static<T>
     */
    private function backtrackToNextSibling(): static
    {
        $up = $this;
        while ($up->isLast() && !$up->isTop()) {
            $up = $up->up();
        }

        if ($up->isTop()) {
            $up->isEnd = true;
            /** @var static<T> $up */
            return $up;
        }

        /** @var static<T> $next */
        $next = $up->right();
        return $next;
    }

    /**
     * Handles removal when the current location is not the first child:
     * the previous sibling becomes the new location and the walk then
     * descends to the rightmost leaf under that subtree.
     *
     * @return static<T>
     */
    private function removeNonFirstSibling(): static
    {
        assert($this->parent instanceof self);

        $siblings = $this->siblings;
        array_splice($siblings, $this->index, 1);
        $index = $this->index - 1;
        if (!isset($siblings[$index])) {
            throw new RuntimeException('Unable to remove node: missing left sibling.');
        }

        $loc = $this->createNewInstance($siblings[$index], $this->parent, $siblings, $index, true, false);
        while ($loc->isBranch() && $loc->hasChildren()) {
            $loc = $loc->down()->rightMost();
        }

        /** @var static<T> $loc */
        return $loc;
    }

    /**
     * Handles removal when the current location is the first child:
     * rebuilds the parent node from the remaining right siblings and
     * returns a zipper positioned at that new parent.
     *
     * @return static<T>
     */
    private function removeFirstChild(): static
    {
        assert($this->parent instanceof self);

        /** @var static<T> $newInstance */
        $newInstance = $this->createNewInstance(
            $this->makeNode($this->parent->getNode(), $this->rights()),
            $this->parent->parent,
            $this->parent->siblings,
            $this->parent->index,
            true,
            $this->parent->isEnd(),
        );

        return $newInstance;
    }
}
