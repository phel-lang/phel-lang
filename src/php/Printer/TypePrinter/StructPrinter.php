<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\Struct\AbstractPersistentStruct;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<AbstractPersistentStruct>
 */
final class StructPrinter implements TypePrinterInterface
{
    private PrinterInterface $printer;

    public function __construct(PrinterInterface $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @param AbstractPersistentStruct $form
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
