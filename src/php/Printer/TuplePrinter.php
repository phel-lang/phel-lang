<?php

declare(strict_types=1);

namespace Phel\Printer;

use Phel\Lang\Tuple;
use Phel\Printer;

final class TuplePrinter
{
    private Printer $printer;

    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
    }

    public function print(Tuple $form): string
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
