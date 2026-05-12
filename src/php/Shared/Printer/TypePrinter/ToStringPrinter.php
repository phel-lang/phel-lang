<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

/**
 * @implements TypePrinterInterface<object>
 */
final class ToStringPrinter implements TypePrinterInterface
{
    /**
     * @param object $form
     */
    public function print(mixed $form): string
    {
        return $form->__toString();
    }
}
