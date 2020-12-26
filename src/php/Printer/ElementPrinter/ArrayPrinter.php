<?php

declare(strict_types=1);

namespace Phel\Printer\ElementPrinter;

final class ArrayPrinter implements ElementPrinterInterface
{
    /**
     * @param mixed $form
     */
    public function print($form): string
    {
        return '<PHP-Array>';
    }
}
