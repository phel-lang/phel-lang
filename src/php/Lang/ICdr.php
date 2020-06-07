<?php

namespace Phel\Lang;

interface ICdr
{

    /**
     * Return the sequence without the first element. If the sequence is empty
     * returns null.
     *
     * @return ICdr
     */
    public function cdr(): ?ICdr;
}
