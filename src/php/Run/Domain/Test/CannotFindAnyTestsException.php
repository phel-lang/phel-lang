<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Test;

use RuntimeException;

final class CannotFindAnyTestsException extends RuntimeException
{
    /**
     * @param list<string> $paths
     */
    public static function inPaths(array $paths): self
    {
        return new self('Cannot find any tests in : ' . implode(',', $paths));
    }
}
