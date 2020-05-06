<?php 

namespace Phel\Token;

class StringToken extends Token {

    public function getTrimedContent() {
        return substr($this->getCode(), 1, -1);
    }
}