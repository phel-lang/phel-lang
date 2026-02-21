<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Printer\Printer;
use Stringable;

abstract class AbstractFn implements FnInterface, MetaInterface, Stringable
{
    use MetaTrait;

    public function __toString(): string
    {
        return Printer::nonReadable()->print($this);
    }
}
