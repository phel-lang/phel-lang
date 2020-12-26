<?php

declare(strict_types=1);

namespace Phel\Printer\ElementPrinter;

use Phel\Lang\PhelArray;
use Phel\Printer\Printer;

/**
 * @implements ElementPrinterInterface<PhelArray>
 */
final class PhelArrayPrinter implements ElementPrinterInterface
{
    private Printer $printer;

    public function __construct(Printer $printer)
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
