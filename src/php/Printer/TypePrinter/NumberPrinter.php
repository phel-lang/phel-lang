<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

/**
 * @implements TypePrinterInterface<int|float>
 */
final class NumberPrinter implements TypePrinterInterface
{
    /**
     * @param int|float $form
     */
    public function print($form): string
    {
        return (string)$form;
    }
}
