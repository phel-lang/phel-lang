<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

/**
 * @implements TypePrinterInterface<object>
 */
final class NonPrintableClassPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param object $form
     */
    public function print($form): string
    {
        return 'Printer cannot print this type: ' . $this->color(get_class($form));
    }

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[1;35m%s\033[0m", $str);
        }

        return $str;
    }
}
