<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\Struct\AbstractPersistentStruct;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<AbstractPersistentStruct>
 */
final readonly class StructPrinter implements TypePrinterInterface
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
            $form->getAllowedKeys(),
        );

        return '(' . $form::class . ' ' . implode(' ', $values) . ')';
    }
}
