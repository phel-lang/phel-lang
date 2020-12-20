<?php

declare(strict_types=1);

namespace Phel\Printer;

use Phel\Lang\Table;
use Phel\Printer;

final class TablePrinter
{
    private Printer $printer;

    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
    }

    public function print(Table $form): string
    {
        $args = [];
        foreach ($form as $key => $value) {
            $args[] = $this->printer->print($key);
            $args[] = $this->printer->print($value);
        }

        return '@{' . implode(' ', $args) . '}';
    }
}
