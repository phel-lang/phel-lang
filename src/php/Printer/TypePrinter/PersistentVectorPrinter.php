<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<PersistentVectorInterface>
 */
final class PersistentVectorPrinter implements TypePrinterInterface
{
    private PrinterInterface $printer;

    public function __construct(PrinterInterface $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @param PersistentVectorInterface $form
     */
    public function print($form): string
    {
        $prefix = '[';
        $suffix = ']';

        $values = [];
        foreach ($form as $element) {
            $values[] = $this->printer->print($element);
        }

        return $prefix . implode(' ', $values) . $suffix;
    }
}
