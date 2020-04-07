<?php

namespace Phel\Lang;

class PhelVar {

    protected $value;

    protected $meta;

    public function __construct($value, Table $meta)
    {
        $this->value = $value;
        $this->meta = $meta;
    }

    public function get() {
        return $this->value;
    }

    public function set($value) {
        return $this->value = $value;
    }

    public function getMeta() {
        return $this->meta;
    }
}