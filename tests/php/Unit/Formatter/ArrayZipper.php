<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter;

use Phel\Formatter\AbstractZipper;

/** @extends AbstractZipper<array<int>> */
final class ArrayZipper extends AbstractZipper
{
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
