<?php

declare(strict_types=1);

namespace Phel\Printer\ElementPrinter;

/**
 * @implements ElementPrinterInterface<object>
 */
final class ObjectPrinter implements ElementPrinterInterface
{
    /**
     * @param object $form
     */
    public function print($form): string
    {
        return '<PHP-Object(' . get_class($form) . ')>';
    }
}
