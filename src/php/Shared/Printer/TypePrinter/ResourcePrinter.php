<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

/**
 * @implements TypePrinterInterface<resource>
 */
final class ResourcePrinter implements TypePrinterInterface
{
    /**
     * @param resource $form
     */
    public function print(mixed $form): string
    {
        /** @psalm-suppress InvalidOperand */
        return '<PHP Resource id #' . $form . '>';
    }
}
