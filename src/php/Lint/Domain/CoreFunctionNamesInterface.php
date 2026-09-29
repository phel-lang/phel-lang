<?php

declare(strict_types=1);

namespace Phel\Lint\Domain;

/**
 * @internal
 */
interface CoreFunctionNamesInterface
{
    /**
     * Whether `$name` is a public, non-macro function of `phel.core`.
     */
    public function contains(string $name): bool;
}
