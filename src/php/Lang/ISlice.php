<?php

namespace Phel\Lang;

interface ISlice
{

    /**
     * Remove values on a indexed data structures.
     *
     * @param int $offset The offset where to start to remove values
     * @param ?int $length The number of how many elements should be removed.
     *
     * @return ISlice
     */
    public function slice(int $offset = 0, ?int $length = null): ISlice;
}
