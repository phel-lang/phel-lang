<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Shared\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<PersistentHashSetInterface<mixed>>
 */
final readonly class PersistentHashSetPrinter implements TypePrinterInterface
{
    public function __construct(private PrinterInterface $printer) {}

    /**
     * @param PersistentHashSetInterface<mixed> $form
     */
    public function print(mixed $form): string
    {
        $prefix = '#{';
        $suffix = '}';

        $values = [];
        foreach ($form as $element) {
            $values[] = $this->printer->print($element);
        }

        return $prefix . implode(' ', $values) . $suffix;
    }
}
