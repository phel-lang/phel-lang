<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Tuple;
use Phel\Printer\Printer;

/**
 * @implements TypePrinterInterface<Tuple>
 */
final class TuplePrinter implements TypePrinterInterface
{
    private Printer $printer;

    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @param Tuple $form
     */
    public function print($form): string
    {
        $prefix = $form->isUsingBracket() ? '[' : '(';
        $suffix = $form->isUsingBracket() ? ']' : ')';

        $values = array_map(
            fn ($elem): string => $this->printer->print($elem),
            $form->toArray()
        );

        return $prefix . implode(' ', $values) . $suffix;
    }
}
