<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter;

use Phel\Formatter\Formatter\AbstractZipper;

/** @extends AbstractZipper<array<int>> */
final class ArrayZipper extends AbstractZipper
{
    /**
     * @param array<int> $root
     *
     * @return ArrayZipper
     */
    public static function createRoot($root): ArrayZipper
    {
        return new self($root, null, [], [], false, false);
    }

    /**
     * @param array<int> $node
     * @param ?AbstractZipper<array<int>> $parent
     * @param array<int>[] $leftSiblings
     * @param array<int>[] $rightSiblings
     *
     * @return static
     */
    protected function createNewInstance(
        $node,
        ?AbstractZipper $parent,
        array $leftSiblings,
        array $rightSiblings,
        bool $hasChanged,
        bool $isEnd
    ) {
        return new self($node, $parent, $leftSiblings, $rightSiblings, $hasChanged, $isEnd);
    }

    public function isBranch(): bool
    {
        return is_array($this->node);
    }

    public function getChildren()
    {
        if (!$this->isBranch()) {
            throw new \Exception('called children on leaf node');
        }

        return $this->node;
    }

    public function makeNode($node, $children)
    {
        return $children;
    }
}
