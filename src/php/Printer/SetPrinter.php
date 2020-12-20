<?php

declare(strict_types=1);

namespace Phel\Printer;

use Phel\Lang\Set;
use Phel\Printer;

final class SetPrinter
{
    private Printer $printer;

    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
    }

    public function print(Set $form): string
    {
        $values = array_map(
            fn ($elem): string => $this->printer->print($elem),
            $form->toPhpArray()
        );

        return '(set' . (count($values) > 0 ? ' ' : '') . implode(' ', $values) . ')';
    }
}
