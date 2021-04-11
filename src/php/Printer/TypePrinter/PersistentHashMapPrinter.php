<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\HashMap\PersistentHashMapInterface;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<PersistentHashMapInterface>
 */
final class PersistentHashMapPrinter implements TypePrinterInterface
{
    private PrinterInterface $printer;

    public function __construct(PrinterInterface $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @param PersistentHashMapInterface $form
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
