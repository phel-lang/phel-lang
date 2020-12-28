<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

/**
 * @implements TypePrinterInterface<bool>
 */
final class BooleanPrinter implements TypePrinterInterface
{
    /**
     * @param bool $form
     */
    public function print($form): string
    {
        return $form === true
            ? 'true'
            : 'false';
    }
}
