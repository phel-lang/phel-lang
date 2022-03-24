<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Printer\PrinterInterface;
use function count;

/**
 * @implements TypePrinterInterface<PersistentHashSetInterface>
 */
final class PersistentHashSetPrinter implements TypePrinterInterface
{
    private PrinterInterface $printer;

    public function __construct(PrinterInterface $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @param PersistentHashSetInterface $form
     */
    public function print(mixed $form): string
    {
        $values = array_map(
            fn ($elem): string => $this->printer->print($elem),
            $form->toPhpArray()
        );

        return '(set' . (count($values) > 0 ? ' ' : '') . implode(' ', $values) . ')';
    }
}
