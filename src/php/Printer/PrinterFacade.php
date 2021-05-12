<?php

declare(strict_types=1);

namespace Phel\Printer;

final class PrinterFacade
{
    private PrinterFactory $printerFactory;

    public function __construct(PrinterFactory $printerFactory)
    {
        $this->printerFactory = $printerFactory;
    }

    public function create(): PrinterInterface
    {
        return $this->printerFactory->createReadablePrinter();
    }
}
