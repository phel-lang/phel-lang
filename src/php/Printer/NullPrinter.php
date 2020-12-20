<?php

declare(strict_types=1);

namespace Phel\Printer;

final class NullPrinter
{
    public function print(): string
    {
        return 'nil';
    }
}
