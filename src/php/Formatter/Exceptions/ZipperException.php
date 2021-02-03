<?php

declare(strict_types=1);

namespace Phel\Formatter\Exceptions;

use RuntimeException;

final class ZipperException extends RuntimeException
{
    public static function calledChildrenOnLeafNode(): self
    {
        return new self('called children on leaf node');
    }

    public static function canReplaceChildrenOnLeafNode(): self
    {
        return new self('can replace children on leaf node');
    }
}
