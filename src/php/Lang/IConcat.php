<?php

namespace Phel\Lang;

interface IConcat
{
    /**
     * Concatenates a value to the data structure.
     *
     * @param mixed $xs The value to concatenate
     *
     * @return IConcat
     */
    public function concat($xs): IConcat;
}
