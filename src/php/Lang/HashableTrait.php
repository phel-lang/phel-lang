<?php

namespace Phel\Lang;

trait HashableTrait
{
    public function hash(): string
    {
        return spl_object_hash($this);
    }
}
