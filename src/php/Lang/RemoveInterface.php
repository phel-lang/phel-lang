<?php

declare(strict_types=1);

namespace Phel\Lang;

interface RemoveInterface
{
    /**
     * Remove values on a indexed data structures.
     *
     * @param int $offset The offset where to start to remove values
     * @param ?int $length The number of how many elements should be removed
     */
    public function remove(int $offset, ?int $length = null): RemoveInterface;
}
