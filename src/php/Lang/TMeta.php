<?php

declare(strict_types=1);

namespace Phel\Lang;

trait TMeta
{
    private ?Table $meta = null;

    public function getMeta(): Table
    {
        if ($this->meta === null) {
            $this->meta = new Table();
        }

        return $this->meta;
    }

    public function setMeta(Table $meta): void
    {
        $this->meta = $meta;
    }
}
