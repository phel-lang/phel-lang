<?php

declare(strict_types=1);

namespace Phel\Printer\ElementPrinter;

/**
 * @implements ElementPrinterInterface<bool>
 */
final class BooleanPrinter implements ElementPrinterInterface
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
