<?php

namespace Phel\Lang;

interface ICons
{

    /**
     * Appends a value to the front of a data structure.
     *
     * @param mixed $x The value to cons
     *
     * @return ICons
     */
    public function cons($x): ICons;
}
