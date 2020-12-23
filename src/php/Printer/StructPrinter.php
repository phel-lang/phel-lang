<?php

declare(strict_types=1);

namespace Phel\Printer;

use Phel\Lang\Struct;
use Phel\Printer;

final class StructPrinter implements PrinterInterface
{
    private Printer $printer;

    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @param Struct $form
     */
    public function print($form): string
    {
        $values = array_map(
            fn ($key): string => $this->printer->print($form[$key]),
            $form->getAllowedKeys()
        );

        return '(' . get_class($form) . ' ' . implode(' ', $values) . ')';
    }
}
