<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use function sprintf;

/**
 * @implements TypePrinterInterface<object>
 */
final class NonPrintableClassPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param object $form
     */
    public function print(mixed $form): string
    {
        return 'Printer cannot print this type: ' . $this->color($form::class);
    }

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[1;35m%s\033[0m", $str);
        }

        return $str;
    }
}
