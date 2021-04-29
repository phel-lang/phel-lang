<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<PersistentMapInterface>
 */
final class PersistentMapPrinter implements TypePrinterInterface
{
    private PrinterInterface $printer;

    public function __construct(PrinterInterface $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @param PersistentMapInterface $form
     */
    public function print($form): string
    {
        $prefix = '{';
        $suffix = '}';

        $values = [];
        foreach ($form as $key => $value) {
            $values[] = $this->printer->print($key);
            $values[] = $this->printer->print($value);
        }

        return $prefix . implode(' ', $values) . $suffix;
    }
}
