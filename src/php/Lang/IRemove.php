<?php

namespace Phel\Lang;

interface IRemove
{

    /**
     * Remove values on a indexed data structures.
     *
     * @param int $offset The offset where to start to remove values
     * @param ?int $length The number of how many elements should be removed.
     *
     * @return IRemove
     */
    public function remove(int $offest, ?int $length = null): IRemove;
}
