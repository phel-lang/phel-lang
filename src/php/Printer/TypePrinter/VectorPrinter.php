<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Collections\Vector\TransientVectorInterface;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<PersistentVectorInterface|TransientVectorInterface>
 */
final class VectorPrinter implements TypePrinterInterface
{
    private PrinterInterface $printer;

    public function __construct(PrinterInterface $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @param PersistentVectorInterface|TransientVectorInterface $form
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
