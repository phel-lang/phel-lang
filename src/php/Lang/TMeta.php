<?php

namespace Phel\Lang;

trait TMeta {

    /**
     * @var ?Table
     */
    private $meta;

    public function getMeta(): Table {
        if ($this->meta == null) {
            $this->meta = new Table();
        }

        return $this->meta;
    }

    public function setMeta(Table $meta) {
        $this->meta = $meta;
    }
}