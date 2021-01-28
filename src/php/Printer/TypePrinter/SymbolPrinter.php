<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Symbol;

/**
 * @implements TypePrinterInterface<Symbol>
 */
final class SymbolPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param Symbol $form
     */
    public function print($form): string
    {
        return $this->color($form->getName());
    }

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[0;91m%s\033[0m", $str);
        }

        return $str;
    }
}
