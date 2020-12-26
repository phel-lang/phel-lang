<?php

declare(strict_types=1);

namespace Phel\Printer\ElementPrinter;

/**
 * @implements ElementPrinterInterface<int|float>
 */
final class NumericalPrinter implements ElementPrinterInterface
{
    /**
     * @param int|float $form
     */
    public function print($form): string
    {
        return (string)$form;
    }
}
