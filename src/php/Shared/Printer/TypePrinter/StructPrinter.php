<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Lang\Collections\Struct\AbstractPersistentStruct;
use Phel\Shared\Munge;
use Phel\Shared\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<AbstractPersistentStruct<mixed>>
 */
final readonly class StructPrinter implements TypePrinterInterface
{
    public function __construct(private PrinterInterface $printer) {}

    /**
     * @param AbstractPersistentStruct<mixed> $form
     */
    public function print(mixed $form): string
    {
        $values = [];
        foreach ($form->getAllowedKeys() as $key) {
            $values[] = $this->printer->print($form[$key]);
        }

        return '(' . Munge::displayNs($form::class) . ' ' . implode(' ', $values) . ')';
    }
}
