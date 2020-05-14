<?php

namespace Phel\Lang;

class PhelVar {

    /**
     * @var mixed
     */
    protected $value;

    /**
     * @var Table
     */
    protected $meta;

    /**
     * Constructor
     * 
     * @param mixed $value The value of the variable.
     * @param Table $meta Meta information.
     */
    public function __construct($value, Table $meta)
    {
        $this->value = $value;
        $this->meta = $meta;
    }

    /**
     * Gets the value of the variable
     * 
     * @return mixed
     */
    public function get() {
        return $this->value;
    }

    /**
     * Set a value to the variable
     * 
     * @param mixed $value The value.
     */
    public function set($value): void {
        $this->value = $value;
    }

    /**
     * Returns the meta data of the variable
     * 
     * @return Table
     */
    public function getMeta(): Table {
        return $this->meta;
    }
}