<?php

namespace Phel\Stream;

class CharData {

    /**
     * @var string
     */
    protected $char;

    /**
     * @var SourceLocation
     */
    protected $location;

    public function __construct($char, $location)
    {
        $this->char = $char;
        $this->location = $location;
    }

    public function getChar() {
        return $this->char;
    }

    public function getLocation() {
        return $this->location;
    }
}