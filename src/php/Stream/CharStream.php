<?php

namespace Phel\Stream;

interface CharStream {

    /**
     * Returns the character that is waiting to be read
     * from the stream
     * 
     * @return CharData|false
     */
    public function peek();

    /**
     * Reads the next character from the stream
     * 
     * @return CharData|false
     */
    public function read();
}