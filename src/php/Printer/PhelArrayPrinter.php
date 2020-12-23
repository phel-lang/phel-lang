<?php

declare(strict_types=1);

namespace Phel\Printer;

use Phel\Lang\PhelArray;
use Phel\Printer;

final class PhelArrayPrinter implements PrinterInterface
{
    private Printer $printer;

    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @psalm-suppress MoreSpecificImplementedParamType
     *
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
