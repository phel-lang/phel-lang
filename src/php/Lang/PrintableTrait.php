<?php

namespace Phel\Lang;

use Phel\Printer;

trait PrintableTrait
{
    public function __toString(): string
    {
        return Printer::readable()->print($this);
    }
}
