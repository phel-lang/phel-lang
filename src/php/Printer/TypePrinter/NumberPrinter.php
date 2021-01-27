<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

/**
 * @implements TypePrinterInterface<int|float>
 */
final class NumberPrinter implements TypePrinterInterface
{
    private bool $withColor;

    public function __construct(bool $withColor = false)
    {
        $this->withColor = $withColor;
    }

    /**
     * @param int|float $form
     */
    public function print($form): string
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
