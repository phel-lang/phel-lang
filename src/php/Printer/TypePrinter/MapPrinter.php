<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Map\TransientMapInterface;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<PersistentMapInterface|TransientMapInterface>
 */
final class MapPrinter implements TypePrinterInterface
{
    private PrinterInterface $printer;

    public function __construct(PrinterInterface $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @param PersistentMapInterface|TransientMapInterface $form
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
