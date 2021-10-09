<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Zipper;

use RuntimeException;

final class ZipperException extends RuntimeException
{
    public static function calledChildrenOnLeafNode(): self
    {
        return new self('Called children on a leaf node');
    }

    public static function cannotReplaceChildrenOnLeafNode(): self
    {
        return new self('Cannot replace children on a leaf node');
    }

    public static function cannotGoRightOnRootNode(): self
    {
        return new self('Cannot go right on the root node');
    }

    public static function cannotGoRightOnLastNode(): self
    {
        return new self('Cannot go right on the last node');
    }

    public static function cannotGoUpOnRootNode(): self
    {
        return new self('Cannot go up on the root node');
    }

    public static function cannotGoDownOnLeafNode(): self
    {
        return new self('Cannot go down on a leaf node');
    }

    public static function cannotGoDownOnNodeWithZeroChildren(): self
    {
        return new self('Cannot go down on a node with zero children');
    }

    public static function cannotInsertLeftOnRootNode(): self
    {
        return new self('Cannot insert left on the root node');
    }

    public static function cannotInsertRightOnRootNode(): self
    {
        return new self('Cannot insert right on the root node');
    }

    public static function cannotRemoveOnRootNode(): self
    {
        return new self('Cannot remove on the root node');
    }

    public static function cannotGoLeftOnRootNode(): self
    {
        return new self('Cannot go left on the root node');
    }

    public static function cannotGoLeftOnTheLeftmostNode(): self
    {
        return new self('Cannot go left on the leftmost node');
    }
}
