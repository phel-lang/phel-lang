<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\PhelArray;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<PhelArray>
 */
final class PhelArrayPrinter implements TypePrinterInterface
{
    private PrinterInterface $printer;

    public function __construct(PrinterInterface $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @param PhelArray $form
     */
    public function print($form): string
    {
        $values = array_map(
            fn ($elem): string => $this->printer->print($elem),
            $form->toPhpArray()
        );

        return '@[' . implode(' ', $values) . ']';
    }
}
