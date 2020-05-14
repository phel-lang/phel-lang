<?php

namespace Phel\Lang;

interface IRest {

    /**
     * Return the sequence without the first element. If the sequence is empty 
     * returns an empty sequence.
     * 
     * @return IRest
     */
    public function rest(): IRest;
}