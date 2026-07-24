<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Lang\BigDecimal;

/**
 * @implements TypePrinterInterface<BigDecimal>
 */
final class BigDecimalPrinter implements TypePrinterInterface
{
    use ColorizeTrait;
    use WithColorTrait;

    private const string COLOR = '0;92';

    /**
     * @param BigDecimal $form
     */
    public function print(mixed $form): string
    {
        return $this->colorize($form->__toString() . 'M', self::COLOR);
    }
}
