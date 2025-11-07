<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\LazySeq\LazySeqInterface;
use Phel\Printer\PrinterInterface;

final readonly class LazySeqPrinter implements TypePrinterInterface
{
    public function __construct(private PrinterInterface $printer)
    {
    }

    /**
     * @param LazySeqInterface $form
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     * @psalm-suppress RawObjectIteration
     */
    public function print(mixed $form): string
    {
        // Convert lazy sequence to array and print it
        $values = [];
        /** @phpstan-ignore foreach.nonIterable */
        foreach ($form as $value) {
            $values[] = $this->printer->print($value);
        }

        return '@[' . implode(' ', $values) . ']';
    }
}
