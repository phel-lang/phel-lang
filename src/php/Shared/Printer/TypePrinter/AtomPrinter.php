<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Lang\Atom;
use Phel\Shared\Printer\PrinterInterface;

/**
 * @implements TypePrinterInterface<Atom<mixed>>
 */
final readonly class AtomPrinter implements TypePrinterInterface
{
    public function __construct(
        private PrinterInterface $printer,
    ) {}

    /**
     * @param Atom<mixed> $form
     */
    public function print(mixed $form): string
    {
        return '(atom ' . $this->printer->print($form->deref()) . ')';
    }
}
