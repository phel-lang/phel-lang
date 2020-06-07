<?php

namespace Phel\Lang;

interface IConcat
{
    /**
     * Concatinates a value to the data structure.
     *
     * @param mixed $xs The value to concatinate
     *
     * @return IConcat
     */
    public function concat($xs): IConcat;
}
