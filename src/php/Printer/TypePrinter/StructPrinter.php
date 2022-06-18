<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\Struct\AbstractPersistentStruct;
use Phel\Printer\PrinterInterface;
use function get_class;

/**
 * @implements TypePrinterInterface<AbstractPersistentStruct>
 */
final class StructPrinter implements TypePrinterInterface
{
    public function __construct(private PrinterInterface $printer)
    {
    }

    /**
     * @param AbstractPersistentStruct $form
     */
    public function print(mixed $form): string
    {
        $values = array_map(
            fn ($key): string => $this->printer->print($form[$key]),
            $form->getAllowedKeys()
        );

        return '(' . get_class($form) . ' ' . implode(' ', $values) . ')';
    }
}
