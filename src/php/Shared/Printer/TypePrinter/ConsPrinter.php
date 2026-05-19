<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Lang\Collections\LazySeq\Cons;
use Phel\Lang\Collections\LazySeq\LazySeqConfig;
use Phel\Shared\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<Cons<mixed>>
 */
final readonly class ConsPrinter implements TypePrinterInterface
{
    public function __construct(private PrinterInterface $printer) {}

    /**
     * @param Cons<mixed> $form
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function print(mixed $form): string
    {
        $values = [];
        $count = 0;
        $limit = LazySeqConfig::REPL_DISPLAY_LIMIT;

        foreach ($form as $value) {
            if ($count >= $limit) {
                $values[] = '...';
                break;
            }

            $values[] = $this->printer->print($value);
            ++$count;
        }

        return '(' . implode(' ', $values) . ')';
    }
}
