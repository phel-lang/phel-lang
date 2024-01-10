<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<PersistentListInterface>
 */
final readonly class PersistentListPrinter implements TypePrinterInterface
{
    public function __construct(private PrinterInterface $printer)
    {
    }

    /**
     * @param PersistentListInterface $form
     */
    public function print(mixed $form): string
    {
        $prefix = '(';
        $suffix = ')';

        $values = [];
        foreach ($form as $element) {
            $values[] = $this->printer->print($element);
        }

        return $prefix . implode(' ', $values) . $suffix;
    }
}
