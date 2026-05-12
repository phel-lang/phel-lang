<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Lang\BigDecimal;

use function sprintf;

/**
 * @implements TypePrinterInterface<BigDecimal>
 */
final class BigDecimalPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param BigDecimal $form
     */
    public function print(mixed $form): string
    {
        return $this->color($form->__toString() . 'M');
    }

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[0;92m%s\033[0m", $str);
        }

        return $str;
    }
}
