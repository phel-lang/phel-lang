<?php

namespace Phel\Lang;

trait CountableTrait
{
    public function count(): int
    {
        return count($this->data);
    }
}
