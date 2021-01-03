<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Table;
use Phel\Printer\Printer;

/**
 * @implements TypePrinterInterface<Table>
 */
final class TablePrinter implements TypePrinterInterface
{
    private Printer $printer;

    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @param Table $form
     */
    public function print($form): string
    {
        $args = [];
        /** @var mixed $value */
        foreach ($form as $key => $value) {
            $args[] = $this->printer->print($key);
            $args[] = $this->printer->print($value);
        }

        return '@{' . implode(' ', $args) . '}';
    }
}
