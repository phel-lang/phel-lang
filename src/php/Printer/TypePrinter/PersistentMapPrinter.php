<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<PersistentMapInterface>
 */
final readonly class PersistentMapPrinter implements TypePrinterInterface
{
    public function __construct(private PrinterInterface $printer)
    {
    }

    /**
     * @param PersistentMapInterface $form
     */
    public function print(mixed $form): string
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
