<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\Queue\PersistentQueue;
use Phel\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<PersistentQueue>
 */
final readonly class PersistentQueuePrinter implements TypePrinterInterface
{
    public function __construct(private PrinterInterface $printer) {}

    /**
     * @param PersistentQueue $form
     */
    public function print(mixed $form): string
    {
        $values = [];
        foreach ($form as $element) {
            $values[] = $this->printer->print($element);
        }

        return '<-(' . implode(' ', $values) . ')-<';
    }
}
