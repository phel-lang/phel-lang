<?php 

namespace Phel\Token;

class EOFToken extends Token {

    public function __construct($location)
    {
        parent::__construct("", $location, $location);
    }
}