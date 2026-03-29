<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Variable;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<Variable>
 */
final readonly class VariablePrinter implements TypePrinterInterface
{
    public function __construct(
        private PrinterInterface $printer,
    ) {}

    /**
     * @param Variable $form
     */
    public function print(mixed $form): string
    {
        return '(var ' . $this->printer->print($form->deref()) . ')';
    }
}
