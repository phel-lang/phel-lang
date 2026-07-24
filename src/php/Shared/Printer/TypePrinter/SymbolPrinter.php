<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Lang\Symbol;

/**
 * @implements TypePrinterInterface<Symbol>
 */
final class SymbolPrinter implements TypePrinterInterface
{
    use ColorizeTrait;
    use WithColorTrait;

    private const string COLOR = '0;91';

    /**
     * @param Symbol $form
     */
    public function print(mixed $form): string
    {
        return $this->colorize($form->getFullName(), self::COLOR);
    }
}
