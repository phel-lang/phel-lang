<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<PersistentVectorInterface>
 */
final readonly class PersistentVectorPrinter implements TypePrinterInterface
{
    public function __construct(private PrinterInterface $printer)
    {
    }

    /**
     * @param PersistentVectorInterface $form
     */
    public function print(mixed $form): string
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
