<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Set;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<Set>
 */
final class SetPrinter implements TypePrinterInterface
{
    private PrinterInterface $printer;

    public function __construct(PrinterInterface $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @param Set $form
     */
    public function print($form): string
    {
        $values = array_map(
            fn ($elem): string => $this->printer->print($elem),
            $form->toPhpArray()
        );

        return '(set' . (count($values) > 0 ? ' ' : '') . implode(' ', $values) . ')';
    }
}
