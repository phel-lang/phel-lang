<?php

declare(strict_types=1);

namespace Phel\Printer\ElementPrinter;

use Phel\Lang\Symbol;

/**
 * @implements ElementPrinterInterface<Symbol>
 */
final class SymbolPrinter implements ElementPrinterInterface
{
    /**
     * @param Symbol $form
     */
    public function print($form): string
    {
        return $form->getName();
    }
}
