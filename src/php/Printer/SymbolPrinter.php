<?php

declare(strict_types=1);

namespace Phel\Printer;

use Phel\Lang\Symbol;

final class SymbolPrinter
{
    public function print(Symbol $form): string
    {
        return $form->getName();
    }
}
