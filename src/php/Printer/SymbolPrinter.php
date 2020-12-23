<?php

declare(strict_types=1);

namespace Phel\Printer;

use Phel\Lang\Symbol;

final class SymbolPrinter implements PrinterInterface
{
    /**
     * @psalm-suppress MoreSpecificImplementedParamType
     *
     * @param Symbol $form
     */
    public function print($form): string
    {
        return $form->getName();
    }
}
