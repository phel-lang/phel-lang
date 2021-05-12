<?php

declare(strict_types=1);

namespace Phel\Printer;

final class PrinterFactory
{
    public function createReadablePrinter(): PrinterInterface
    {
        return new Printer($readable = true);
    }

    public function createNonReadablePrinter(): PrinterInterface
    {
        return new Printer($readable = false);
    }

    public function createConReadablePrinterWithColor(): PrinterInterface
    {
        return new Printer($readable = false, $withColor = true);
    }
}
