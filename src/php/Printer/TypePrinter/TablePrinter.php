<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Table;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<Table>
 */
final class TablePrinter implements TypePrinterInterface
{
    private PrinterInterface $printer;

    public function __construct(PrinterInterface $printer)
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
