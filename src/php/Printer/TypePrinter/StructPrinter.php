<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\AbstractStruct;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<AbstractStruct>
 */
final class StructPrinter implements TypePrinterInterface
{
    private PrinterInterface $printer;

    public function __construct(PrinterInterface $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @param AbstractStruct $form
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
