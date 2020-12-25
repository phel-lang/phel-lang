<?php

declare(strict_types=1);

namespace Phel\Printer;

/**
 * @implements PrinterInterface<int|float>
 */
final class NumericalPrinter implements PrinterInterface
{
    /**
     * @param int|float $form
     */
    public function print($form): string
    {
        return (string)$form;
    }
}
