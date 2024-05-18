<?php

declare(strict_types=1);

namespace Phel\Lang;

interface IdenticalInterface
{
    /**
     * Checks if $other is identical to $this.
     */
    public function identical(mixed $other): bool;
}
