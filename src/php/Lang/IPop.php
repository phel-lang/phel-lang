<?php

namespace Phel\Lang;

interface IPop {

    /**
     * Removes the value at the beginning of a sequence and return this removed 
     * value.
     * 
     * @return mixed
     */
    public function pop();
}