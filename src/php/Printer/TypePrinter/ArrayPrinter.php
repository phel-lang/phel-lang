<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

final class ArrayPrinter implements TypePrinterInterface
{
    /**
     * @param mixed $form
     */
    public function print($form): string
    {
        return '<PHP-Array>';
    }
}
