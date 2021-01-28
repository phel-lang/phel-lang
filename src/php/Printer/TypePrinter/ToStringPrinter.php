<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

/**
 * @implements TypePrinterInterface<object>
 */
final class ToStringPrinter implements TypePrinterInterface
{
    /**
     * @param object $form
     */
    public function print($form): string
    {
        return $form->__toString();
    }
}
