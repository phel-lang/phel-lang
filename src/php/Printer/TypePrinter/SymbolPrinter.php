<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Symbol;

/**
 * @implements TypePrinterInterface<Symbol>
 */
final class SymbolPrinter implements TypePrinterInterface
{
    /**
     * @param Symbol $form
     */
    public function print($form): string
    {
        return $form->getName();
    }
}
