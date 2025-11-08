<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Collections\LazySeq\LazySeqConfig;
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
        // Realize up to REPL_DISPLAY_LIMIT elements to prevent infinite realization
        $values = [];
        $count = 0;
        $limit = LazySeqConfig::REPL_DISPLAY_LIMIT;

        /** @phpstan-ignore foreach.nonIterable */
        foreach ($form as $value) {
            if ($count >= $limit) {
                $values[] = '...';
                break;
            }

            $values[] = $this->printer->print($value);
            ++$count;
        }

        return '@[' . implode(' ', $values) . ']';
    }
}
