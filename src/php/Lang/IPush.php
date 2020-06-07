<?php

namespace Phel\Lang;

interface IPush
{

    /**
     * Pushes a new value of the data structure.
     *
     * @param mixed $x The new value.
     *
     * @return IPush
     */
    public function push($x): IPush;
}
