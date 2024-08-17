<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use function sprintf;

/**
 * @implements TypePrinterInterface<int|float>
 */
final class NumberPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param int|float $form
     */
    public function print(mixed $form): string
    {
        return $this->color((string)$form);
    }

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[0;92m%s\033[0m", $str);
        }

        return $str;
    }
}
